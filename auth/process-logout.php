<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ../index.php');
    exit;
}

logoutUser();

header('Location: register.php');
exit;