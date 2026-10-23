<?php
declare(strict_types=1);

header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; navigate-to 'self'; img-src 'self' https://placehold.co data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; form-action 'self'");

require_once __DIR__ . '/includes/auth.php';

// Controlla se la richiesta è AJAX (per SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Le pagine statiche sono pubbliche: le richieste normali ricevono il layout completo.
if (!$isAjax) {
    $pageTitle = 'Chi siamo - StartUpFlow';
    $currentPage = 'chi-siamo';
    $bodyClass = 'dashboard-page';
    $additionalCss = '<link rel="stylesheet" href="/assets/css/chi-siamo.css">';
    $email = '';
    $createdAt = '';
    require_once __DIR__ . '/includes/layout/header.php';
    echo '<main class="main">';
}

// Se è AJAX, recupera dati utente e restituisci solo il contenuto
if ($isAjax) {
    global $pdo;
    $userId = currentUserId();
    $username = $_SESSION['username'] ?? 'Utente';
} else {
    // Non-AJAX, non loggato: pagina completa già avviata con header
    global $pdo;
    $userId = null;
    $username = 'Utente';
}

?>
<div class="page-content">
    <h1>Chi siamo</h1>
    
    <p>
        StartupFlow è una piattaforma innovativa progettata per semplificare la gestione finanziaria e amministrativa delle startup e delle piccole imprese.
        Nati dall'esigenza di automatizzare processi spesso complessi e dispendiosi, offriamo soluzioni intuitive che permettono agli imprenditori di concentrarsi su ciò che conta davvero: far crescere il proprio business.
    </p>
    
    <h2>La nostra missione</h2>
    <p>
        Vogliamo democraticizzare l'accesso a strumenti professionali di gestione aziendale, rendendoli accessibili a tutti, indipendentemente dalle dimensioni o dal settore di appartenenza.
        Crediamo che ogni impresa meriti di avere il controllo completo sulla propria salute finanziaria, senza dover investire in soluzioni costose e complesse.
    </p>
    
    <h2>Il nostro team</h2>
    <div class="team-grid">
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Nikka Cantorna">
            <h3>Nikka</h3>
            <p>Developer</p>
        </div>
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Luisa Bianchi">
            <h3>Presley</h3>
            <p>Developer</p>
        </div>
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Marco Verdi">
            <h3>Rafid</h3>
            <p>Developer</p>
        </div>
        <div class="team-card">
            <img src="/assets/img/GianLucaDev.jpg" alt="Gian Luca Vitale">
            <h3>Gian Luca</h3>
            <p>Developer</p>
        </div>
    </div>
    
    
</div>

<?php
// Chiudi la pagina completa per le richieste non-AJAX.
if (!$isAjax) {
    echo '</main>';
    require_once __DIR__ . '/includes/layout/footer.php';
}
?>
