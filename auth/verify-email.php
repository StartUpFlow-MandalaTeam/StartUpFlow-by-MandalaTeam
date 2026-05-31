<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Read and sanitize the token from the URL query parameters for email verification
$token = trim($_GET['token'] ?? '');

// Reject empty tokens immediately to avoid unnecessary database queries and potential security issues
if ($token === '') {
    header('Location: login.php?error=verification_invalid');
    exit;
}

// Hash the token using SHA-256 to securely compare against the stored hash in the database
$tokenHash = hash('sha256', $token);

try {// Look up the email verification record in the database using the hashed token
    $stmt = $pdo->prepare('SELECT ev.id AS verification_id, ev.user_id, ev.expires_at, ev.used_at FROM email_verifications ev WHERE ev.token_hash = :token_hash LIMIT 1');
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    // If no matching record is found, or if the token has already been used, or if it has expired, reject the verification attempt
    if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) < time()) {
        header('Location: login.php?error=verification_invalid');
        exit;
    }

    // Use a transaction so user activation and token consumption stay in sync
    $pdo->beginTransaction();

    // Activate the user's account by setting is_active to 1, and reset any failed login attempts or locks just in case
    $activateUser = $pdo->prepare('UPDATE users SET is_active = 1, failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
    $activateUser->execute([':id' => $row['user_id']]);

    // Mark the email verification record as used
    $markUsed = $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = :id');
    $markUsed->execute([':id' => $row['verification_id']]);

    $pdo->commit();

    header('Location: login.php?verified=1');
    exit;
} catch (PDOException $e) {// If any database error occurs, roll back the transaction if it's still active and redirect with an error message
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: login.php?error=verification_invalid');
    exit;
}
