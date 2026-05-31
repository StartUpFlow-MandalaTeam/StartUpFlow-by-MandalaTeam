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
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Dashboard</title>
	<link rel="stylesheet" href="assets/css/navbar.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300..700&display=swap">
	<script src="assets/js/mobile-nav.js" defer></script>
	<script src="assets/js/profile-modal.js" defer></script>
	
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #fff;
			font-family: Arial, sans-serif;
			color: #111;
		}
	</style>
</head>
<body>
<!-- Navbar component -->
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
				<span class="dashboard-navbar__user"><?php echo $username; ?></span>
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
					<a href="#modulo-1">Link 1</a>
					<a href="#modulo-2">Link 2</a>
					<a href="#modulo-3">Link 3</a>
					<a href="#modulo-4">Link 4</a>
					<a href="#modulo-5">Link 5</a>
					<a href="#modulo-6">Link 6</a>
					<a href="#modulo-7">Link 7</a>
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
		<h1>Ciao, <?php echo $username; ?></h1>
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
			</footer>
		</form>
	</dialog>
</body>
</html>
