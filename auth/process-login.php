<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?error=csrf');
    exit;
}

$identifier = trim($_POST['Username'] ?? '');
$password = $_POST['Password'] ?? '';

if ($identifier === '' || $password === '') {
    header('Location: login.php?error=empty');
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT id, username, email, password_hash, is_active, failed_login_attempts, locked_until
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ');
    $stmt->execute([
        ':username' => $identifier,
        ':email' => $identifier,
    ]);

    $user = $stmt->fetch();

    if ($user && (int) $user['is_active'] !== 1) {
        header('Location: login.php?error=unverified');
        exit;
    }

    if ($user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        header('Location: login.php?error=locked');
        exit;
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($user) {
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCK_MINUTES * 60));
            }

            $failed = $pdo->prepare('
                UPDATE users
                SET failed_login_attempts = :attempts,
                    locked_until = :locked_until
                WHERE id = :id
            ');
            $failed->execute([
                ':attempts' => $attempts,
                ':locked_until' => $lockUntil,
                ':id' => $user['id'],
            ]);

            if ($lockUntil !== null) {
                header('Location: login.php?error=locked');
                exit;
            }
        }

        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    if (!empty($_POST['remember_me'])) {
        clearRememberMeToken();
        issueRememberMeToken((int) $user['id']);
    } else {
        clearRememberMeToken();
    }

    $update = $pdo->prepare('
        UPDATE users
        SET last_login_at = NOW()
            , failed_login_attempts = 0
            , locked_until = NULL
        WHERE id = :id
    ');
    $update->execute([
        ':id' => $user['id'],
    ]);

    header('Location: ../index.php');
    exit;
} catch (PDOException $e) {
    header('Location: login.php?error=database');
    exit;
}