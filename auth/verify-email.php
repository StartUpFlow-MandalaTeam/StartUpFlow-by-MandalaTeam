<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    header('Location: login.php?error=verification_invalid');
    exit;
}

$tokenHash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare('SELECT ev.id AS verification_id, ev.user_id, ev.expires_at, ev.used_at FROM email_verifications ev WHERE ev.token_hash = :token_hash LIMIT 1');
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) < time()) {
        header('Location: login.php?error=verification_invalid');
        exit;
    }

    $pdo->beginTransaction();

    $activateUser = $pdo->prepare('UPDATE users SET is_active = 1, failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
    $activateUser->execute([':id' => $row['user_id']]);

    $markUsed = $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = :id');
    $markUsed->execute([':id' => $row['verification_id']]);

    $pdo->commit();

    header('Location: login.php?verified=1');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: login.php?error=verification_invalid');
    exit;
}
