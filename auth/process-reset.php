<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

// Only accept POST requests for reset processing to prevent CSRF and other issues
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reset-password.php');
    exit;
}

// Validate the CSRF token to protect against cross-site request forgery attacks
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: reset-password.php?error=csrf');
    exit;
}

// Helper to build redirect URLs that preserve the token in case of errors during the reset process
$buildResetErrorRedirect = static function (string $error, ?string $token = null): string {
    $location = 'reset-password.php?error=' . urlencode($error);

    if ($token !== null && $token !== '') {
        $location .= '&token=' . urlencode($token);
    }

    return $location;
};

// Request reset by email
if (!empty($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: reset-password.php?sent=1');
        exit;
    }

    try {// Find user by email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {// Generate a password reset token, store it in the database, and send the reset link via email
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :th, :exp)');
            $ins->execute([
                ':uid' => $user['id'],
                ':th' => $tokenHash,
                ':exp' => $expires,
            ]);

            $resetLink = appBaseUrlFromRequest() . '/auth/reset-password.php?token=' . $token;

            // Try to send/reset via mail helper (will fallback to logging link in dev)
            $res = sendResetEmail($email, $resetLink);

            // Always show neutral confirmation. In dev we may expose debug_link.
            if (!empty($res['debug_link']) && defined('APP_ENV') && APP_ENV === 'dev') {
                header('Location: reset-password.php?sent=1&debug_link=' . urlencode($res['debug_link']));
                exit;
            }

            header('Location: reset-password.php?sent=1');
            exit;
        }

        // No user found
        header('Location: reset-password.php?sent=1');
        exit;
    } catch (PDOException $e) {// On database error, log the error and show a neutral confirmation message to avoid exposing details
        header('Location: reset-password.php?sent=1');
        exit;
    }
}

// Perform password reset with token + new password
$token = $_POST['token'] ?? '';
$password = $_POST['Password'] ?? '';
$cpassword = $_POST['CPassword'] ?? '';

// Ensure that the token, password, and confirm password are not empty
if ($token === '' || $password === '' || $cpassword === '') {
    header('Location: ' . $buildResetErrorRedirect('empty', $token));
    exit;
}
// Confirm password match
if ($password !== $cpassword) {
    header('Location: ' . $buildResetErrorRedirect('password_mismatch', $token));
    exit;
}

// Enforce strong password requirements (at least 8 characters, including uppercase, lowercase, number, and special character)
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    header('Location: ' . $buildResetErrorRedirect('password_short', $token));
    exit;
}

// Compare token hash to stored value
$tokenHash = hash('sha256', $token);

try {//Load reset row and associated user
    $stmt = $pdo->prepare('SELECT pr.id AS pr_id, pr.user_id, pr.expires_at, pr.used_at, u.id AS uid, u.password_hash FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = :th LIMIT 1');
    $stmt->execute([':th' => $tokenHash]);
    $row = $stmt->fetch();

    // Validate token: exists, not used, not expired
    if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) < time()) {
        header('Location: reset-password.php?error=invalid_token');
        exit;
    }

    // Ensure the new password is not the same as the old password
    if (password_verify($password, (string) $row['password_hash'])) {
        header('Location: ' . $buildResetErrorRedirect('password_reused', $token));
        exit;
    }

    // Hash the new password and update it in the database, while also marking the reset token as used to prevent reuse
    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $u = $pdo->prepare('UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :id');
    $u->execute([':ph' => $newHash, ':id' => $row['user_id']]);

    $mark = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $mark->execute([':id' => $row['pr_id']]);

    $pdo->commit();

    // After successful password reset, redirect the user to the login page with a success message
    header('Location: login.php?reset=1');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: ' . $buildResetErrorRedirect('database', $token));
    exit;
}
