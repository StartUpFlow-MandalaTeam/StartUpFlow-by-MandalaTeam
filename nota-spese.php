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
<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-button">← Torna alla Dashboard</a>
        <h1>Nota Spese</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>
    <div class="service-content">
        <div class="alert alert-info">
            <strong>Modulo Nota Spese</strong>
            <p>Questo spazio è riservato al team di sviluppo per implementare il servizio.</p>
        </div>
    </div>
</div>
