<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Controlla se la richiesta è AJAX (per SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Le pagine statiche sono pubbliche: le richieste normali ricevono il layout completo.
if (!$isAjax) {
    $pageTitle = 'Servizi - StartUpFlow';
    $currentPage = 'servizi';
    $bodyClass = 'dashboard-page';
    $additionalCss = '<link rel="stylesheet" href="/assets/css/servizi.css">';
    $email = '';
    $createdAt = '';
    require_once __DIR__ . '/includes/layout/header.php';
    echo '<main class="main">';
}
// Se è AJAX, recupera dati utente
if ($isAjax) {
    global $pdo;
    $userId = currentUserId();
    $username = $_SESSION['username'] ?? 'Utente';
}

?>

<div class="page-content">
    <h1>I nostri servizi</h1>
    
    <p>
        StartupFlow offre una suite completa di strumenti progettati per semplificare ogni aspetto della gestione aziendale.
        Dalla fatturazione al monitoraggio finanziario, abbiamo tutto ciò di cui hai bisogno per tenerti organizzato e concentrato sulla crescita.
    </p>
    
    <h2>Moduli disponibili</h2>
    <div class="services-grid">
        <div class="service-card">
            <span class="material-symbols-outlined">receipt_long</span>
            <h3>Fatturazione</h3>
            <p>Crea, gestisci e invia fatture professionali in pochi click. Con template personalizzabili e tracciamento dei pagamenti.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">calendar_month</span>
            <h3>Scadenzario</h3>
            <p>Tieni sotto controllo tutte le scadenze: pagamenti, incassi, tasse. Con notifiche automatiche e promemoria.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">paid</span>
            <h3>Nota Spese</h3>
            <p>Registra e categorizza ogni spesa aziendale. Genera report dettagliati e monitora i costi in tempo reale.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">monitoring</span>
            <h3>Burn Rate Meter</h3>
            <p>Misura la salute finanziaria della tua startup. Analizza il consumo di cassa e proietta la tua runway.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">timer</span>
            <h3>Time Tracker</h3>
            <p>Traccia il tempo dedicato a ogni cliente e progetto. Genera report per la fatturazione a tempo.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">description</span>
            <h3>Zero Carta</h3>
            <p>Archivia digitalmente tutti i tuoi documenti. Organizzato, ricercabile e sempre accessibile.</p>
</div>
        <div class="service-card">
            <span class="material-symbols-outlined">group</span>
            <h3>CRM Clienti</h3>
            <p>Gestisci il ciclo completo di acquisizione clienti. Dalle lead alla fidelizzazione, con storico delle interazioni.</p>
</div>
</div>
    
    <h2>Caratteristiche principali</h2>
    <ul class="features-list">
        <li>Accesso da qualsiasi dispositivo, ovunque ti trovi</li>
        <li>Dati sempre sincronizzati e protetti</li>
        <li>Interfaccia intuitiva e facile da usare</li>
        <li>Reportistica avanzata e personalizzabile</li>
        <li>Integrazione con i principali servizi di pagamento</li>
        <li>Supporto dedicato e aggiornamenti costanti</li>
    </ul>
    

</div>

<?php
// Chiudi la pagina completa per le richieste non-AJAX.
if (!$isAjax) {
    echo '</main>';
    require_once __DIR__ . '/includes/layout/footer.php';
}
?>
