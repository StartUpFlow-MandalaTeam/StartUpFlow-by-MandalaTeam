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
<div class="page-content spa-page">
    <h1>Contattaci</h1>
    
    <p>
        Hai domande, suggerimenti o necessiti di supporto? Siamo qui per aiutarti.
        Compila il modulo sottostante o utilizza uno dei nostri canali di contatto preferiti.
    </p>
    
    <h2>Canali di contatto</h2>
    <div class="contact-section">
        <div class="contact-method">
            <span class="material-symbols-outlined">mail</span>
            <div>
                <h3>Email</h3>
                <p><a href="mailto:supporto@startupflow.it">supporto@startupflow.it</a></p>
            <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="contact-method">
            <span class="material-symbols-outlined">call</span>
            <div>
                <h3>Telefono</h3>
                <p>+39 02 1234 5678</p>
            <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="contact-method">
            <span class="material-symbols-outlined">schedule</span>
            <div>
                <h3>Orari di ufficio</h3>
                <p>Lun-Ven: 9:00 - 18:00</p>
            <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="contact-method">
            <span class="material-symbols-outlined">location_on</span>
            <div>
                <h3>Indirizzo</h3>
                <p>Via Roma 123, 20100 Milano (MI)</p>
            <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
    <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
    
    <h2>Invia un messaggio</h2>
    <form class="contact-form" action="#" method="post">
        <div class="form-group">
            <label for="nome">Nome e Cognome</label>
            <input type="text" id="nome" name="nome" required placeholder="Mario Rossi">
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="mario.rossi@esempio.it">
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="form-group">
            <label for="telefono">Telefono (opzionale)</label>
            <input type="tel" id="telefono" name="telefono" placeholder="+39 333 1234567">
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="form-group">
            <label for="oggetto">Oggetto</label>
            <select id="oggetto" name="oggetto" required>
                <option value="" selected disabled>Seleziona un oggetto</option>
                <option value="supporto">Supporto tecnico</option>
                <option value="informazioni">Richiesta informazioni</option>
                <option value="commerciale">Contatto commerciale</option>
                <option value="feedback">Feedback e suggerimenti</option>
                <option value="altro">Altro</option>
            </select>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <div class="form-group">
            <label for="messaggio">Messaggio</label>
            <textarea id="messaggio" name="messaggio" required placeholder="Scrivi qui il tuo messaggio..."></textarea>
        <style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>
        
        <button type="submit">Invia messaggio</button>
    </form>
    
    <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
<style>.back-link { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #000; color: #fff; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 4px; border: 1px solid #000; transition: all 0.2s; }.back-link:hover { background: #333; border-color: #000; }</style>
</div>

<style>
    <?php include __DIR__ . '/assets/css/contatti.css'; ?>
</style>
