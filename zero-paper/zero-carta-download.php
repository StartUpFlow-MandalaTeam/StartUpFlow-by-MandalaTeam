<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Accesso non autorizzato.');
}

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($documentId === false || $documentId === null || $documentId < 1) {
    http_response_code(400);
    exit('Documento non valido.');
}

global $pdo;
$userId = (int) currentUserId();
$statement = $pdo->prepare(
    'SELECT d.id, d.file_path, d.type, c.id AS client_id
     FROM documents d
     INNER JOIN clients c ON c.id = d.client_id
     WHERE d.id = :document_id AND c.user_id = :user_id
     LIMIT 1'
);
$statement->execute([':document_id' => $documentId, ':user_id' => $userId]);
$document = $statement->fetch();
if (!$document) {
    http_response_code(404);
    exit('Documento non trovato.');
}

$expectedPrefix = 'storage/contracts/' . $userId . '/';
if (!str_starts_with((string) $document['file_path'], $expectedPrefix)) {
    http_response_code(404);
    exit('Documento non trovato.');
}
$projectRoot = dirname(__DIR__);
$storageRoot = realpath($projectRoot . '/storage/contracts');
$filePath = realpath($projectRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $document['file_path']));
if ($storageRoot === false || $filePath === false
    || !str_starts_with($filePath, $storageRoot . DIRECTORY_SEPARATOR)
    || !is_file($filePath)) {
    http_response_code(404);
    exit('Documento non trovato.');
}

$storedName = basename($filePath);
$downloadName = preg_replace('/^(?:\d{8}-\d{6}-[a-f0-9]{16}-|[a-f0-9]{16}-)/', '', $storedName) ?: $storedName;
$downloadName = str_replace(["\r", "\n", '"'], '', $downloadName);
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt' => 'application/vnd.oasis.opendocument.text',
];
$extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . (string) filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
