<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
    exit;
}

$userId = currentUserId();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}

// Rate limiting: separate limits for account and IP
$ip = client_ip();
$action = 'change_email_request';
$maxAttemptsUser = 5; // per account
$maxAttemptsIP = 20;  // per IP

// count attempts for this user in the window
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM audit_logs WHERE user_id = :user_id AND action = :action AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([':user_id' => $userId, ':action' => $action]);
$rowUser = $stmt->fetch();
$countUser = $rowUser ? (int)$rowUser['cnt'] : 0;

if ($countUser >= $maxAttemptsUser) {
    audit_log($userId, $action, ['status' => 'rate_limited_user', 'count' => $countUser], $ip);
    echo json_encode(['success' => false, 'message' => 'Troppe richieste per account. Riprova più tardi.']);
    exit;
}

// count attempts for this IP in the window
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM audit_logs WHERE ip_address = :ip AND action = :action AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([':ip' => $ip, ':action' => $action]);
$rowIp = $stmt->fetch();
$countIp = $rowIp ? (int)$rowIp['cnt'] : 0;

if ($countIp >= $maxAttemptsIP) {
    audit_log($userId, $action, ['status' => 'rate_limited_ip', 'count' => $countIp], $ip);
    echo json_encode(['success' => false, 'message' => 'Troppe richieste da questo IP. Riprova più tardi.']);
    exit;
}
$newEmail = filter_var((string)($_POST['new_email'] ?? ''), FILTER_VALIDATE_EMAIL);
$currentPassword = (string)($_POST['current_password'] ?? '');

if (!$newEmail) {
    audit_log($userId, $action, ['status' => 'invalid_email', 'new_email' => $_POST['new_email'] ?? ''], $ip);
    echo json_encode(['success' => false, 'message' => 'Email non valida']);
    exit;
}

// Verify current password
$stmt = $pdo->prepare('SELECT password_hash, email FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$row = $stmt->fetch();

if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
    audit_log($userId, $action, ['status' => 'bad_password'], $ip);
    echo json_encode(['success' => false, 'message' => 'Password non corretta']);
    exit;
}

// Check new email uniqueness
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $newEmail]);
if ($stmt->fetch()) {
    audit_log($userId, $action, ['status' => 'email_in_use', 'new_email' => $newEmail], $ip);
    echo json_encode(['success' => false, 'message' => 'Email già in uso']);
    exit;
}

// Create token and store request
$token = bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24); // 24h

$insert = $pdo->prepare('INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at) VALUES (:user_id, :new_email, :token_hash, :expires_at)');
$insert->execute([
    ':user_id' => $userId,
    ':new_email' => $newEmail,
    ':token_hash' => $tokenHash,
    ':expires_at' => $expiresAt,
]);

$requestId = (int)$pdo->lastInsertId();

audit_log($userId, $action, ['status' => 'created', 'new_email' => $newEmail, 'request_id' => $requestId], $ip);

$base = appBaseUrlFromRequest();
$link = $base . '/auth/confirm-change-email.php?id=' . $requestId . '&token=' . $token;

// Send verification email to the new address
$sent = sendChangeEmailVerification($newEmail, $link);

if (!$sent['sent']) {
    // cleanup request
    $del = $pdo->prepare('DELETE FROM email_change_requests WHERE id = :id');
    $del->execute([':id' => $requestId]);
    audit_log($userId, $action, ['status' => 'smtp_failed', 'error' => $sent['error']], $ip);
    echo json_encode(['success' => false, 'message' => 'Impossibile inviare email: ' . $sent['error']]);
    exit;
}

// Optionally notify the old email that a change was requested
$oldEmail = (string)$row['email'];
if ($oldEmail !== '' && $oldEmail !== $newEmail) {
    sendChangeEmailNotice($newEmail, $oldEmail);
}

audit_log($userId, $action, ['status' => 'email_sent'], $ip);

echo json_encode(['success' => true, 'message' => 'Se la richiesta è valida, riceverai una email di verifica.']);
exit;
