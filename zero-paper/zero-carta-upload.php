<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function zeroCartaUploadResponse(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isLoggedIn()) {
    zeroCartaUploadResponse(401, ['success' => false, 'message' => 'Accedi per caricare un documento.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    zeroCartaUploadResponse(405, ['success' => false, 'message' => 'Metodo non consentito.']);
}
$csrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($csrfToken) || !validateCsrfToken($csrfToken)) {
    zeroCartaUploadResponse(403, ['success' => false, 'message' => 'Sessione scaduta. Ricarica la pagina e riprova.']);
}

$clientIdInput = $_POST['client_id'] ?? null;
$clientId = is_string($clientIdInput) ? filter_var($clientIdInput, FILTER_VALIDATE_INT) : false;
$type = $_POST['type'] ?? '';
if ($clientId === false || $clientId === null || $clientId < 1
    || !is_string($type) || !in_array($type, ['NDA', 'Contract'], true)) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Seleziona un tipo di documento e un cliente validi.']);
}
if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Seleziona un file da caricare.']);
}

$file = $_FILES['document'];
$uploadErrors = [
    UPLOAD_ERR_INI_SIZE => 'Il file supera il limite di caricamento del server.',
    UPLOAD_ERR_FORM_SIZE => 'Il file supera il limite di 10 MB.',
    UPLOAD_ERR_PARTIAL => 'Il caricamento del file è incompleto. Riprova.',
    UPLOAD_ERR_NO_FILE => 'Seleziona un file da caricare.',
    UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea di caricamento non disponibile.',
    UPLOAD_ERR_CANT_WRITE => 'Impossibile salvare il file sul server.',
    UPLOAD_ERR_EXTENSION => 'Il server ha bloccato il caricamento del file.',
];
if (!isset($file['error']) || !is_int($file['error'])) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il file caricato non è valido.']);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = $uploadErrors[$file['error']] ?? 'Caricamento del file non riuscito.';
    zeroCartaUploadResponse(400, ['success' => false, 'message' => $message]);
}
if (!isset($file['size'], $file['tmp_name'], $file['name'])
    || !is_int($file['size']) || !is_string($file['tmp_name']) || !is_string($file['name'])
    || !is_uploaded_file($file['tmp_name'])) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il file caricato non è valido.']);
}
if ($file['size'] > 10 * 1024 * 1024) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il file non può superare 10 MB.']);
}

$extension = strtolower(pathinfo(str_replace('\\', '/', $file['name']), PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'docx'];
if (!in_array($extension, $allowedExtensions, true)) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Formato non supportato. Usa solo PDF o DOCX.']);
}

$fileHandle = fopen($file['tmp_name'], 'rb');
$signature = $fileHandle === false ? false : fread($fileHandle, 8);
if (is_resource($fileHandle)) {
    fclose($fileHandle);
}
$validSignature = match ($extension) {
    'pdf' => is_string($signature) && str_starts_with($signature, '%PDF-'),
    'docx' => is_string($signature) && str_starts_with($signature, "PK\x03\x04"),
    default => false,
};
if (!$validSignature) {
    zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il contenuto del file non corrisponde al formato selezionato.']);
}
if ($extension === 'docx' && class_exists('ZipArchive')) {
    $archive = new ZipArchive();
    if ($archive->open($file['tmp_name']) !== true) {
        zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il file non è un documento valido del formato selezionato.']);
    }
    $hasExpectedDocument = $archive->locateName('word/document.xml') !== false;
    $archive->close();
    if (!$hasExpectedDocument) {
        zeroCartaUploadResponse(400, ['success' => false, 'message' => 'Il file non è un documento valido del formato selezionato.']);
    }
}

$userId = (int) currentUserId();
global $pdo;
$clientStatement = $pdo->prepare('SELECT id FROM clients WHERE id = :client_id AND user_id = :user_id LIMIT 1');
$clientStatement->execute([':client_id' => $clientId, ':user_id' => $userId]);
if (!$clientStatement->fetch()) {
    zeroCartaUploadResponse(404, ['success' => false, 'message' => 'Cliente non trovato.']);
}

$originalName = basename(str_replace('\\', '/', $file['name']));
$originalStem = pathinfo($originalName, PATHINFO_FILENAME);
$safeStem = preg_replace('/[^A-Za-z0-9._ -]/', '-', $originalStem);
$safeStem = trim(substr((string) $safeStem, 0, 100), '.-_ ');
if ($safeStem === '') {
    $safeStem = 'documento';
}
$safeName = $safeStem . '.' . $extension;
$fileName = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '-' . $safeName;
$relativePath = 'storage/contracts/' . $userId . '/' . $fileName;
$projectRoot = dirname(__DIR__);
$destinationDirectory = $projectRoot . '/storage/contracts/' . $userId;
if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0750, true) && !is_dir($destinationDirectory)) {
    error_log('Zero Carta upload: unable to create the contract storage directory.');
    zeroCartaUploadResponse(500, ['success' => false, 'message' => 'Impossibile preparare l’archivio dei documenti.']);
}
if (!move_uploaded_file($file['tmp_name'], $destinationDirectory . '/' . $fileName)) {
    error_log('Zero Carta upload: unable to move the uploaded file.');
    zeroCartaUploadResponse(500, ['success' => false, 'message' => 'Impossibile salvare il file caricato.']);
}

try {
    $pdo->beginTransaction();
    $documentStatement = $pdo->prepare(
        'INSERT INTO documents (client_id, type, file_path) VALUES (:client_id, :type, :file_path)'
    );
    $documentStatement->execute([
        ':client_id' => $clientId,
        ':type' => $type,
        ':file_path' => $relativePath,
    ]);
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($destinationDirectory . '/' . $fileName)) {
        unlink($destinationDirectory . '/' . $fileName);
    }
    error_log('Zero Carta upload database error: ' . $exception->getMessage());
    zeroCartaUploadResponse(500, ['success' => false, 'message' => 'Impossibile registrare il documento. Riprova più tardi.']);
}

zeroCartaUploadResponse(201, ['success' => true]);
