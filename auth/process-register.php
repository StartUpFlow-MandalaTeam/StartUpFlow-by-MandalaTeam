<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: register.php?error=csrf');
    exit;
}

$username = trim($_POST['Username'] ?? '');
$email = trim($_POST['Email'] ?? '');
$password = $_POST['Password'] ?? '';
$confirmPassword = $_POST['CPassword'] ?? '';

if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
    header('Location: register.php?error=empty');
    exit;
}

if (strlen($username) < 6) {
    header('Location: register.php?error=username_short');
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    header('Location: register.php?error=username_invalid');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=email_invalid');
    exit;
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    header('Location: register.php?error=password_short');
    exit;
}

if ($password !== $confirmPassword) {
    header('Location: register.php?error=password_mismatch');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
    ]);

    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existingUser) {
        $pdo->rollBack();

        if (strcasecmp($existingUser['email'] ?? '', $email) === 0) {
            header('Location: register.php?error=email_exists');
        } elseif (strcasecmp($existingUser['username'] ?? '', $username) === 0) {
            header('Location: register.php?error=username_exists');
        } else {
            header('Location: register.php?error=exists');
        }

        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, is_active)
        VALUES (:username, :email, :password_hash, 0)
    ');

    $insert->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);

    $userId = (int) $pdo->lastInsertId();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 86400);

    $verification = $pdo->prepare('
        INSERT INTO email_verifications (user_id, token_hash, expires_at)
        VALUES (:user_id, :token_hash, :expires_at)
    ');
    $verification->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expires,
    ]);

    $pdo->commit();

    $verificationLink = appBaseUrlFromRequest() . '/auth/verify-email.php?token=' . $token;
    $mailResult = sendVerificationEmail($email, $verificationLink, $username);

    if (!$mailResult['sent']) {
        $cleanup = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $cleanup->execute([':id' => $userId]);
        header('Location: register.php?error=verification_email_failed');
        exit;
    }

    header('Location: login.php?registered=1');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: register.php?error=database');
    exit;
}