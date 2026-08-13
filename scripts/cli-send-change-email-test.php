<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

$userId = 10; // giorgia
$newEmail = 'cli-test+' . bin2hex(random_bytes(4)) . '@example.com';
$token = bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24);

$insert = $pdo->prepare('INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at, created_at) VALUES (:user_id, :new_email, :token_hash, :expires_at, NOW())');
$insert->execute([
    ':user_id' => $userId,
    ':new_email' => $newEmail,
    ':token_hash' => $tokenHash,
    ':expires_at' => $expiresAt,
]);

$requestId = (int)$pdo->lastInsertId();
$link = 'http://localhost:8000/auth/confirm-change-email.php?id=' . $requestId . '&token=' . $token;

echo "Created request id={$requestId} new_email={$newEmail}\n";

$sent = sendChangeEmailVerification($newEmail, $link);
var_export($sent);

// notify old email
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$u = $stmt->fetch();
$oldEmail = $u['email'] ?? '';
if ($oldEmail !== '') {
    $notice = sendChangeEmailNotice($newEmail, $oldEmail);
    echo "\nNotice send result:\n";
    var_export($notice);
}

echo "\nLink: {$link}\n";
