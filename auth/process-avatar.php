<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

if (!isLoggedIn() || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Richiesta non autorizzata']);
    exit;
}

$file = $_FILES['avatar'] ?? null;
$maxSize = 5 * 1024 * 1024;
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Seleziona un’immagine valida']);
    exit;
}

if (!isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'Upload non valido']);
    exit;
}

if ((int) $file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'L’immagine non può superare 5 MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
if ($finfo) {
    finfo_close($finfo);
}

if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'Formato immagine non supportato']);
    exit;
}

$avatarDirectory = dirname(__DIR__) . '/uploads/avatars';
if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossibile preparare la cartella avatar']);
    exit;
}

$filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
$absolutePath = $avatarDirectory . '/' . $filename;
$relativePath = 'uploads/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossibile salvare l’immagine']);
    exit;
}

try {
    $userId = currentUserId();
    $stmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Utente non trovato');
    }

    $update = $pdo->prepare('UPDATE users SET avatar_path = :avatar_path, updated_at = NOW() WHERE id = :id');
    $update->execute([
        ':avatar_path' => $relativePath,
        ':id' => $userId,
    ]);

    $oldPath = (string) ($user['avatar_path'] ?? '');
    if ($oldPath !== '' && str_starts_with($oldPath, 'uploads/avatars/')) {
        $oldAbsolutePath = dirname(__DIR__) . '/' . $oldPath;
        if (is_file($oldAbsolutePath)) {
            unlink($oldAbsolutePath);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Immagine avatar aggiornata',
        'avatar_url' => '/' . $relativePath,
    ]);
} catch (Throwable $exception) {
    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Impossibile aggiornare il profilo']);
}
