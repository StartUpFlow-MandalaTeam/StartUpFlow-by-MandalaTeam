<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Se non è autenticato, reindirizza
if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

// Controlla se la richiesta è AJAX (per SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Se NON è AJAX, reindirizza a index.php (per accesso diretto)
if (!$isAjax) {
    header('Location: /');
    exit;
}

// Recupera dati utente (opzionale, per personalizzare)
global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

?>
<div class="service-page">
    <header class="service-header">
        <a href="/" data-spa="true" class="back-button">← Torna alla Dashboard</a>
        <h1>Fatturazione</h1>
        <p>Benvenuto, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</p>
    </header>

    <div class="service-content">
        <div class="alert alert-info">
            <strong>Modulo Fatturazione</strong>
            <p>Questo spazio è riservato al team di sviluppo per implementare il servizio.</p>
        </div>
    </div>
</div>
