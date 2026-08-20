<?php

$pageTitle = 'Contatti - StartUpFlow';
$currentPage = 'contatti';

$additionalCss = '<link rel="stylesheet" href="/assets/css/contatti.css">';

require_once __DIR__ . '/../includes/layout/header.php';

?>

	<main class="main">
		<div class="page-content">
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
					</div>
				</div>
				
				<div class="contact-method">
					<span class="material-symbols-outlined">call</span>
					<div>
						<h3>Telefono</h3>
						<p>+39 02 1234 5678</p>
					</div>
				</div>
				
				<div class="contact-method">
					<span class="material-symbols-outlined">schedule</span>
					<div>
						<h3>Orari di ufficio</h3>
						<p>Lun-Ven: 9:00 - 18:00</p>
					</div>
				</div>
				
				<div class="contact-method">
					<span class="material-symbols-outlined">location_on</span>
					<div>
						<h3>Indirizzo</h3>
						<p>Via Roma 123, 20100 Milano (MI)</p>
					</div>
				</div>
			</div>
			
			<h2>Invia un messaggio</h2>
			<form class="contact-form" action="#" method="post">
				<div class="form-group">
					<label for="nome">Nome e Cognome</label>
					<input type="text" id="nome" name="nome" required placeholder="Mario Rossi">
				</div>
				
				<div class="form-group">
					<label for="email">Email</label>
					<input type="email" id="email" name="email" required placeholder="mario.rossi@esempio.it">
				</div>
				
				<div class="form-group">
					<label for="telefono">Telefono (opzionale)</label>
					<input type="tel" id="telefono" name="telefono" placeholder="+39 333 1234567">
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
				</div>
				
				<div class="form-group">
					<label for="messaggio">Messaggio</label>
					<textarea id="messaggio" name="messaggio" required placeholder="Scrivi qui il tuo messaggio..."></textarea>
				</div>
				
				<button type="submit">Invia messaggio</button>
			</form>
			
			<a href="/" class="back-link">← Torna alla Dashboard</a>
		</div>
	</main>
	
<?php
require_once __DIR__ . '/../includes/layout/footer.php';
