<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

function appBaseUrlFromRequest(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';

    if (!empty($_SERVER['HTTP_HOST'])) {
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    return rtrim(APP_URL, '/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reset-password.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: reset-password.php?error=csrf');
    exit;
}

// Request reset by email
if (!empty($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: reset-password.php?sent=1');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
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
            require_once __DIR__ . '/../includes/mail.php';
            $res = sendResetEmail($email, $resetLink);

            // Always show neutral confirmation. In dev we may expose debug_link.
            if (!empty($res['debug_link']) && defined('APP_ENV') && APP_ENV === 'dev') {
                header('Location: reset-password.php?sent=1&debug_link=' . urlencode($res['debug_link']));
                exit;
            }

            header('Location: reset-password.php?sent=1');
            exit;
        }

        header('Location: reset-password.php?sent=1');
        exit;
    } catch (PDOException $e) {
        header('Location: reset-password.php?sent=1');
        exit;
    }
}

// Perform reset with token + new password
$token = $_POST['token'] ?? '';
$password = $_POST['Password'] ?? '';
$cpassword = $_POST['CPassword'] ?? '';

if ($token === '' || $password === '' || $cpassword === '') {
    header('Location: reset-password.php?error=invalid_token');
    exit;
}

if ($password !== $cpassword || strlen($password) < 6) {
    header('Location: reset-password.php?error=invalid_token');
    exit;
}

$tokenHash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare('SELECT pr.id AS pr_id, pr.user_id, pr.expires_at, pr.used_at, u.id AS uid FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = :th LIMIT 1');
    $stmt->execute([':th' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) < time()) {
        header('Location: reset-password.php?error=invalid_token');
        exit;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $u = $pdo->prepare('UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :id');
    $u->execute([':ph' => $newHash, ':id' => $row['user_id']]);

    $mark = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $mark->execute([':id' => $row['pr_id']]);

    $pdo->commit();

    header('Location: login.php?reset=1');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: reset-password.php?error=invalid_token');
    exit;
}
