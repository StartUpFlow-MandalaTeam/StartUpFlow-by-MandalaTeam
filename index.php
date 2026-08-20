<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// If the user is not logged in, show the registration page first
if (!isLoggedIn()) {
	header('Location: /auth/register.php');
	exit;
}

// Fetch user profile data from the database
global $pdo;

$userId = currentUserId();
$profile = [
	'username' => $_SESSION['username'] ?? 'utente',
	'email' => '',
	'created_at' => '',
];

if ($userId !== null) {
	$stmt = $pdo->prepare('SELECT username, email, created_at FROM users WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $userId]);
	$userRow = $stmt->fetch();

	if ($userRow) {
		$profile = [
			'username' => (string) $userRow['username'],
			'email' => (string) $userRow['email'],
			'created_at' => (string) $userRow['created_at'],
		];
	}
}
// Sanitize user data for safe output in HTML contexts to prevent XSS attacks.
$username = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8');
$createdAt = $profile['created_at'] !== '' ? htmlspecialchars(date('d/m/Y H:i', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8') : '';

// Array delle card con titolo, descrizione e link (route React)
$cards = [
	['title' => 'Fatturazione', 'description' => 'Emetti le tue fatture', 'link' => '/fatturazione'],
	['title' => 'Scadenzario', 'description' => 'Pagamenti & Incassi', 'link' => '/scadenzario'],
	['title' => 'Nota Spese', 'description' => 'Registra le uscite', 'link' => '/nota-spese'],
	['title' => 'Burn Rate Meter', 'description' => 'Il tuo stato di salute', 'link' => '/burn-rate-meter'],
	['title' => 'Time-Tracker', 'description' => 'Tempo di fatturazione per cliente', 'link' => '/time-tracker'],
	['title' => '"Zero carta"', 'description' => 'Repository documentale', 'link' => '/zero-carta'],
	['title' => 'CRM clienti', 'description' => 'Ciclo di acquisizione clienti', 'link' => '/crm-clienti'],
];

// Dati utente per React (da usare quando la SPA sarà operativa)
$userDataForReact = [
	'username' => $profile['username'],
	'email' => $profile['email'],
	'createdAt' => $profile['created_at']
];
$userDataJson = json_encode($userDataForReact, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Dashboard</title>
	<link rel="stylesheet" href="assets/css/navbar.css">
	<link rel="stylesheet" href="assets/css/card-container.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300..700&family=Montserrat:wght@400;500;600&display=swap">
	<script src="assets/js/mobile-nav.js" defer></script>
	<script src="assets/js/profile-modal.js" defer></script>
	
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			background: #d6a800;
			font-family: Arial, sans-serif;
			color: #111;
		}
		
		/* Stili per React SPA */
		#root {
			width: 100%;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}
		
		/* Nascondi contenuto statico quando React è caricato */
		.static-fallback {
			display: none;
		}
		
		.has-react .static-fallback {
			display: none;
		}
		
		.no-react .static-fallback {
			display: flex;
			min-height: 100vh;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			width: 100%;
		}
		
		.no-react .static-fallback .main {
			width: 100%;
			max-width: 1200px;
			margin: 0 auto;
			padding-top: 100px; /* Spazio per la navbar fixed */
		}
		
		.has-react #root {
			display: flex;
		}
		
		.no-react #root {
			display: none;
		}
	</style>
	
	<!-- Dati utente per React (SPA) -->
	<script>window.__USER_DATA__ = <?php echo $userDataJson; ?>;</script>
	
	<!-- Carica React bundle (quando disponibile) -->
	<?php if (file_exists(__DIR__ . '/dist/assets/main.js')): ?>
		<script type="module" src="/dist/assets/main.js"></script>
	<?php endif; ?>
	
	<script>
		// Gestione fallback PHP/React
		// Di default, mostriamo il contenuto statico
		document.documentElement.classList.add('no-react');
		
		// Funzione per verificare se React è caricato
		function checkReactLoaded() {
			// Se abbiamo React globale OPPURE il root ha contenuto (Vite dev server)
			if (typeof window.React !== 'undefined' || document.getElementById('root').children.length > 0) {
				document.documentElement.classList.remove('no-react');
				document.documentElement.classList.add('has-react');
				return true;
			}
			return false;
		}
		
		// Verifica iniziale
		checkReactLoaded();
		
		// Verifica periodicamente (per Vite dev server che carichi asincrono)
		const reactCheckInterval = setInterval(checkReactLoaded, 100);
		
		// Fermati dopo 5 secondi (se React non si carichi, mostriamo il fallback)
		setTimeout(() => {
			clearInterval(reactCheckInterval);
			// Se ancora non abbiamo React, assicurati che il fallback sia visibile
			if (!document.documentElement.classList.contains('has-react')) {
				document.documentElement.classList.add('no-react');
			}
		}, 5000);
	</script>
</head>
<body>
<!-- Root per React SPA -->
<div id="root"></div>

<!-- Contenuto statico di fallback (visibile solo se React non è caricato) -->
<div class="static-fallback">
	<header class="dashboard-navbar" aria-label="Primary navigation">
		<button type="button" class="dashboard-navbar__toggle" data-mobile-nav-toggle aria-controls="mobile-nav-drawer" aria-expanded="false" aria-label="Apri menu">
			<span></span>
			<span></span>
			<span></span>
		</button>

		<div class="dashboard-navbar__brand" aria-label="StartupFlow home">
			<span class="dashboard-navbar__logo">SF</span>
		</div>

		<nav class="dashboard-navbar__links" aria-label="Static pages">
			<a href="#chi-siamo">Chi siamo</a>
			<a href="#servizi">Servizi</a>
			<a href="#contatti">Contatti</a>
		</nav>

		<div class="dashboard-navbar__userarea">
			<button type="button" class="dashboard-navbar__user-trigger" data-profile-modal-open aria-haspopup="dialog" aria-controls="profile-modal" aria-expanded="false">
				<div class="dashboard-navbar__user-avatar" aria-hidden="true">
					<span class="material-symbols-outlined">person</span>
				</div>
				<span class="dashboard-navbar__user"><?php echo "Ciao, " . $username; ?></span>
			</button>
			<form class="dashboard-navbar__logout" action="auth/process-logout.php" method="post">
				<?php echo csrfInput(); ?>
				<button type="submit" class="dashboard-navbar__logout-btn" aria-label="Logout">
					<span class="material-symbols-outlined" aria-hidden="true">logout</span>
				</button>
			</form>
		</div>
	</header>
<!-- Mobile navigation drawer -->
	<div class="dashboard-navbar__backdrop" data-mobile-nav-backdrop hidden></div>

	<aside class="dashboard-navbar__drawer" id="mobile-nav-drawer" aria-label="Menu mobile" aria-hidden="true">
		<div class="dashboard-navbar__drawer-top">
			<span class="dashboard-navbar__logo">SF</span>
			<button type="button" class="dashboard-navbar__drawer-close" data-mobile-nav-close aria-label="Chiudi menu">
				<span class="material-symbols-outlined" aria-hidden="true">close</span>
			</button>
		</div>
<!-- Static page links in mobile drawer -->
		<div class="dashboard-navbar__drawer-content">
			<div class="dashboard-navbar__drawer-section">
				<nav class="dashboard-navbar__drawer-links" aria-label="Static pages mobile">
					<a href="#chi-siamo">Chi siamo</a>
					<a href="#servizi">Servizi</a>
					<a href="#contatti">Contatti</a>
				</nav>
			</div>

		<div class="dashboard-navbar__drawer-separator" aria-hidden="true"></div>
<!-- Module links in mobile drawer -->
			<div class="dashboard-navbar__drawer-section">
				<nav class="dashboard-navbar__drawer-modules" aria-label="Moduli mobile">
					<a href="/fatturazione">Fatturazione</a>
					<a href="/scadenzario">Scadenzario</a>
					<a href="/nota-spese">Nota Spese</a>
					<a href="/burn-rate-meter">Burn Rate Meter</a>
					<a href="/time-tracker">Time-Tracker</a>
					<a href="/zero-carta">"Zero carta"</a>
					<a href="/crm-clienti">CRM clienti</a>
				</nav>
			</div>
		</div>

		<div class="dashboard-navbar__drawer-separator" aria-hidden="true"></div>
<!-- User actions in mobile drawer -->
		<div class="dashboard-navbar__drawer-actions">
			<button type="button" class="dashboard-navbar__drawer-profile" data-profile-modal-open aria-haspopup="dialog" aria-controls="profile-modal" aria-expanded="false" aria-label="Apri profilo utente">
				<div class="dashboard-navbar__user-avatar" aria-hidden="true">
					<span class="material-symbols-outlined">person</span>
				</div>
			</button>
			<form class="dashboard-navbar__drawer-logout" action="auth/process-logout.php" method="post">
				<?php echo csrfInput(); ?>
				<button type="submit" class="dashboard-navbar__logout-btn" aria-label="Logout">
					<span class="material-symbols-outlined" aria-hidden="true">logout</span>
				</button>
			</form>
		</div>
	</aside>
<!-- Main content area -->
	<div class="main">
		<div class="card-container">
			<!-- Riga superiore: prime 4 card -->
			<div class="card-row card-row--top">
				<?php foreach (array_slice($cards, 0, 4) as $card): ?>
					<div class="card">
						<img class="card-image" src="https://placehold.co/290x180" alt="Placeholder">
						<div class="card-content">
							<h3 class="card-title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
							<p class="card-description"><?php echo htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8'); ?></p>
							<a href="<?php echo htmlspecialchars($card['link'], ENT_QUOTES, 'UTF-8'); ?>" class="card-button">Vai!</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Riga inferiore: ultime 3 card -->
			<div class="card-row card-row--bottom">
				<?php foreach (array_slice($cards, 4) as $card): ?>
					<div class="card">
						<img class="card-image" src="https://placehold.co/290x180" alt="Placeholder">
						<div class="card-content">
							<h3 class="card-title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
							<p class="card-description"><?php echo htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8'); ?></p>
							<a href="<?php echo htmlspecialchars($card['link'], ENT_QUOTES, 'UTF-8'); ?>" class="card-button">Vai!</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<!-- User profile modal dialog -->
	<dialog class="profile-modal" id="profile-modal" aria-labelledby="profile-modal-title">
		<form class="profile-modal__panel" method="dialog">
			<header class="profile-modal__header">
				<div>
					<p class="profile-modal__eyebrow">Dati profilo</p>
					<h2 id="profile-modal-title">Anagrafica utente</h2>
				</div>
				<button type="button" class="profile-modal__close" data-profile-modal-close aria-label="Chiudi modale">
					<span class="material-symbols-outlined" aria-hidden="true">close</span>
				</button>
			</header>

			<div class="profile-modal__grid">
				<label class="profile-modal__field">
					<span>Username</span>
					<input type="text" value="<?php echo $username; ?>" readonly>
				</label>
				<label class="profile-modal__field">
					<span>Email</span>
					<input type="email" value="<?php echo $email; ?>" readonly>
				</label>
				<label class="profile-modal__field">
					<span>Data di creazione</span>
					<input type="text" value="<?php echo $createdAt; ?>" readonly>
				</label>
				<div class="profile-modal__divider" aria-hidden="true"></div>
				<label class="profile-modal__field">
					<span>Nome</span>
					<input type="text" name="nome" value="" placeholder="Inserisci il nome">
				</label>
				<label class="profile-modal__field">
					<span>Cognome</span>
					<input type="text" name="cognome" value="" placeholder="Inserisci il cognome">
				</label>
				<label class="profile-modal__field">
					<span>Data di nascita</span>
					<input type="date" name="data_di_nascita" value="">
				</label>
				<label class="profile-modal__field">
					<span>Luogo di nascita</span>
					<input type="text" name="luogo_di_nascita" value="" placeholder="Inserisci il luogo di nascita">
				</label>
				<label class="profile-modal__field">
					<span>Stato di nascita</span>
					<select name="stato" id="stato_nascita" aria-label="Stato di nascita" data-countries-select>
						<option value="" selected>Seleziona uno Stato</option>
					</select>
				</label>
				<label class="profile-modal__field">
					<span>Codice fiscale</span>
					<input type="text" name="codice_fiscale" value="" placeholder="Inserisci il codice fiscale">
				</label>
				<label class="profile-modal__field profile-modal__field--full">
					<span>Indirizzo</span>
					<input type="text" name="indirizzo" value="" placeholder="Inserisci l'indirizzo">
				</label>
				<label class="profile-modal__field">
					<span>Città</span>
					<input type="text" name="citta" value="" placeholder="Inserisci la città">
				</label>
				<label class="profile-modal__field">
					<span>CAP</span>
					<input type="text" name="cap" value="" placeholder="Inserisci il CAP" inputmode="numeric" maxlength="5">
				</label>
			</div>

			<footer class="profile-modal__actions">
				<button type="button" class="profile-modal__save">Salva</button>
				<button type="button" class="profile-modal__change-email" data-change-email-open>Modifica email</button>
			</footer>
		</form>
	</dialog>

	<!-- Change Email dialog (top-level, not nested) -->
	<dialog class="change-email-modal" id="change-email-modal" aria-labelledby="change-email-title">
		<form class="change-email-panel" method="post" action="/auth/process-change-email.php" id="change-email-form">
			<header class="change-email-header">
				<div>
					<h2 id="change-email-title">Cambia email</h2>
				</div>
			</header>

			<div class="change-email-body">
				<?php echo csrfInput(); ?>
				<label class="profile-modal__field">
					<span>Nuova email</span>
					<input type="email" name="new_email" required autocomplete="email">
				</label>

				<label class="profile-modal__field">
					<span>Password corrente</span>
					<input type="password" name="current_password" required autocomplete="current-password">
				</label>

				<p class="hint">Ti invieremo un link di verifica alla nuova email. Conferma entro 24 ore.</p>
			</div>

			<footer class="change-email-actions">
				<button type="submit" class="profile-modal__save">Invia verifica</button>
				<button type="button" class="profile-modal__cancel" data-change-email-cancel>Annulla</button>
			</footer>
		</form>
	</dialog>
</div> <!-- Fine static-fallback -->
</body>
</html>
