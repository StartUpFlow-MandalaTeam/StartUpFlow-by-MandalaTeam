<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

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
    die('All fields are required.');
}

if (strlen($username) < 6) {
    die('Username must be at least 6 characters.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email address.');
}

if (strlen($password) < 6) {
    die('Password must be at least 6 characters.');
}

if ($password !== $confirmPassword) {
    die('Passwords do not match.');
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
    ]);

    if ($stmt->fetch()) {
        header('Location: login.php?error=exists');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('
        INSERT INTO users (username, email, password_hash)
        VALUES (:username, :email, :password_hash)
    ');

    $insert->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);

    header('Location: login.php?registered=1');
    exit;
} catch (PDOException $e) {
    die('Registration failed: ' . $e->getMessage());
}