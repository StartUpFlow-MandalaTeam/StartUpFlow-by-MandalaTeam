<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Se non è autenticato, reindirizza
if (!isLoggedIn()) {
    header('Location: /auth/register.php');
    exit;
}

// Controlla se la richiesta è AJAX (per SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Se NON è AJAX, reindirizza a /
if (!$isAjax) {
    header('Location: /');
    exit;
}

// Recupera dati utente
global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';

// Se è AJAX, restituisci solo il contenuto senza header/footer
?>
<div class="page-content spa-page">
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
            <img src="https://placehold.co/120x120" alt="Mario Rossi">
            <h3>Mario Rossi</h3>
            <p>CEO & Founder</p>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Luisa Bianchi">
            <h3>Luisa Bianchi</h3>
            <p>CTO</p>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Marco Verdi">
            <h3>Marco Verdi</h3>
            <p>Lead Developer</p>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <div class="team-card">
            <img src="https://placehold.co/120x120" alt="Sara Neri">
            <h3>Sara Neri</h3>
            <p>Product Manager</p>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
    <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
    
    <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
<style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>

<style>
    <?php include __DIR__ . '/assets/css/chi-siamo.css'; ?>
</style>
