<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Only accept POST requests for logout processing to prevent CSRF and other issues
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// Validate the CSRF token to protect against cross-site request forgery attacks
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ../index.php');
    exit;
}

// Clear the user's session and any "remember me" tokens to log them out securely
logoutUser();

// Redirect the user to the login page after logging out
header('Location: login.php');
exit;