<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    header('Location: /');
    exit;
}
global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
?>

<link rel="stylesheet" href="/assets/css/burn-rate-meter.css">

<!-- Breadcrumb (desktop) -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="/" data-spa="true">Dashboard</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Burn Rate Meter</span>
</nav>

<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
        <h1>Burn Rate Meter</h1>
    </header>
</div>

