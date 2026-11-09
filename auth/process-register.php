<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

// Only accept POST requests for registration processing to prevent CSRF and other issues
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// Validate the CSRF token to protect against cross-site request forgery attacks
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: register.php?error=csrf');
    exit;
}

//Collect and sanitize form input
$username = trim($_POST['Username'] ?? '');
$email = trim($_POST['Email'] ?? '');
$password = $_POST['Password'] ?? '';
$confirmPassword = $_POST['CPassword'] ?? '';

// Ensure that the username, email, password, and confirm password are not empty
if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
    header('Location: register.php?error=empty');
    exit;
}
// Username must be at least 6 characters and contain only letters, numbers, and underscores
if (strlen($username) < 6) {
    header('Location: register.php?error=username_short');
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    header('Location: register.php?error=username_invalid');
    exit;
}
// Email format validation using PHP's built-in filter
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=email_invalid');
    exit;
}
// Password must be at least 8 characters and include uppercase, lowercase, number, and special character
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    header('Location: register.php?error=password_short');
    exit;
}
// Confirm password matches
if ($password !== $confirmPassword) {
    header('Location: register.php?error=password_mismatch');
    exit;
}

try {// Use a transaction to ensure atomicity of the registration process
    $pdo->beginTransaction();

    // Check if the username or email already exists in the database to prevent duplicates
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
    ]);

    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existingUser) {
        $pdo->rollBack();

        // Provide specific error messages for existing username or email to guide the user, but avoid revealing which one exists for security reasons
        if (strcasecmp($existingUser['email'] ?? '', $email) === 0) {
            header('Location: register.php?error=email_exists');
        } elseif (strcasecmp($existingUser['username'] ?? '', $username) === 0) {
            header('Location: register.php?error=username_exists');
        } else {
            header('Location: register.php?error=exists');
        }

        exit;
    }

    // Hash the password securely using PHP's password_hash function with the default algorithm (currently bcrypt)
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert the new user into the database with is_active set to 0 (inactive) until email verification is completed
    $insert = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, is_active)
        VALUES (:username, :email, :password_hash, 0)
    ');

    $insert->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);

    // Create an email verification token and persist its hash in the database
    $userId = (int) $pdo->lastInsertId();
    $token = bin2hex(random_bytes(32));                 // Generate a secure random token for email verification
    $tokenHash = hash('sha256', $token);                // Hash the token before storing it in the database for security    
    $expires = date('Y-m-d H:i:s', time() + 86400);     // Set token expiration to 24 hours from now

    $verification = $pdo->prepare('
        INSERT INTO email_verifications (user_id, token_hash, expires_at)
        VALUES (:user_id, :token_hash, :expires_at)
    ');
    $verification->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expires,
    ]);

    // Creation of user's profile
    $profileCreation = $pdo->prepare('
        INSERT INTO users_profiles (user_id)
        VALUES (:user_id)
    ');
    $profileCreation->execute([
        ':user_id' => $userId
    ]);

    $pdo->commit();

    //Build the verification link and send it via the mail helper
    $verificationLink = appBaseUrlFromRequest() . '/auth/verify-email.php?token=' . $token;
    $mailResult = sendVerificationEmail($email, $verificationLink, $username);

    // If the email failed to send, remove the user from the database
    if (!$mailResult['sent']) {
        $cleanup = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $cleanup->execute([':id' => $userId]);
        header('Location: register.php?error=verification_email_failed');
        exit;
    }

    //On successful registration, redirect to the login page with a success message prompting the user to check their email for the verification link
    header('Location: login.php?registered=1');
    exit;
} catch (PDOException $e) {// If any database error occurs, roll back the transaction and redirect back to the registration page with a generic error message
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: register.php?error=database');
    exit;
}