<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// Fetch user profile data from the database only if logged in
$username = '';
$email = '';
$createdAt = '';
$avatarUrl = '';
if (isLoggedIn()) {
	global $pdo;
	$userId = currentUserId();
	$profile = [
		'username' => $_SESSION['username'] ?? 'utente',
		'email' => '',
		'created_at' => '',
		'avatar_path' => '',
	];

	if ($userId !== null) {
		$stmt = $pdo->prepare('SELECT username, email, created_at, avatar_path FROM users WHERE id = :id LIMIT 1');
		$stmt->execute([':id' => $userId]);
		$userRow = $stmt->fetch();
		getUsersInfo($userId);

		if ($userRow) {
			$profile = [
				'username' => (string) $userRow['username'],
				'email' => (string) $userRow['email'],
				'created_at' => (string) $userRow['created_at'],
				'avatar_path' => (string) ($userRow['avatar_path'] ?? ''),

				'first_name' => (string) ($_SESSION['first_name'] ?? ''),
				'last_name' => (string) ($_SESSION['last_name'] ?? ''),
				'birth_date' => (string) ($_SESSION['birth_date'] ?? ''),
				'birth_place' => (string) ($_SESSION['birth_place'] ?? ''),
				'birth_country' => (string) ($_SESSION['birth_country'] ?? ''),
				'cf' => (string) ($_SESSION['cf'] ?? ''),
				'address' => (string) ($_SESSION['address'] ?? ''),
				'city' => (string) ($_SESSION['city'] ?? ''),
				'postal_code' => (string) ($_SESSION['postal_code'] ?? ''),
			];
		}
		
	}

	// Sanitize user data for safe output in HTML contexts to prevent XSS attacks.
	$username = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8');
	$email = htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8');

	$firstName = htmlspecialchars($profile['first_name'], ENT_QUOTES, 'UTF-8');
	$lastName = htmlspecialchars($profile['last_name'], ENT_QUOTES, 'UTF-8');
	$birthDate = htmlspecialchars($profile['birth_date'], ENT_QUOTES, 'UTF-8');
	$birthPlace = htmlspecialchars($profile['birth_place'], ENT_QUOTES, 'UTF-8');
	$birthCountry = htmlspecialchars($profile['birth_country'], ENT_QUOTES, 'UTF-8');
	$cf = htmlspecialchars($profile['cf'], ENT_QUOTES, 'UTF-8');
	$address = htmlspecialchars($profile['address'], ENT_QUOTES, 'UTF-8');
	$city = htmlspecialchars($profile['city'], ENT_QUOTES, 'UTF-8');
	$postalCode = htmlspecialchars($profile['postal_code'], ENT_QUOTES, 'UTF-8');

	$createdAt = $profile['created_at'] !== ''
		? htmlspecialchars(date('d/m/Y H:i', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8')
		: '';
	if ($profile['avatar_path'] !== '') {
		$avatarUrl = htmlspecialchars('/' . ltrim($profile['avatar_path'], '/'), ENT_QUOTES, 'UTF-8');
	}
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($pageTitle ?? 'StartupFlow', ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23ffffff'/><path d='M 30,50 C 20,20 80,10 70,40 C 60,70 10,60 25,80 C 40,100 90,80 80,50 C 70,20 20,30 35,15 C 50,0 85,35 65,65 C 45,95 15,40 45,30 C 75,20 85,75 50,75 C 15,75 25,35 55,45 Z' fill='none' stroke='%23111111' stroke-width='5' stroke-linecap='round'/></svg>">
	<link rel="stylesheet" href="/assets/css/navbar.css">
	<link rel="stylesheet" href="/assets/css/card-container.css">
	<link rel="stylesheet" href="/assets/css/page-common.css">
	<link rel="stylesheet" href="/assets/css/style.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300..700&family=Montserrat:wght@400;500;600&display=swap">
	<?php echo ($additionalCss ?? ''); ?>
	<script src="/assets/js/mobile-nav.js" defer></script>
	<script src="/assets/js/profile-modal.js" defer></script>
	<script src="/assets/js/fatturazione.js" defer></script>
	
	<?php echo $additionalHead ?? ''; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClass ?? '', ENT_QUOTES, 'UTF-8'); ?>">
	<?php require_once __DIR__ . '/modals.php'; ?>
	<header class="dashboard-navbar" aria-label="Primary navigation">
		<button type="button" class="dashboard-navbar__toggle" data-mobile-nav-toggle aria-controls="mobile-nav-drawer" aria-expanded="false" aria-label="Apri menu">
			<span></span>
			<span></span>
			<span></span>
		</button>

		<a href="<?php echo isLoggedIn() ? '/' : '/?no-overlay=1'; ?>" class="dashboard-navbar__brand" aria-label="StartupFlow home">
			<span class="dashboard-navbar__logo"><img class="dashboard-navbar__logo-image" src="/assets/img/startupflow-mark.png" alt="StartUpFlow"></span>
		</a>

		<nav class="dashboard-navbar__links" aria-label="Static pages">
			<a href="/chi-siamo.php" <?php echo ($currentPage ?? '') === 'chi-siamo' ? 'class="active"' : ''; ?> data-spa="true">Chi siamo</a>
			<a href="/servizi.php" <?php echo ($currentPage ?? '') === 'servizi' ? 'class="active"' : ''; ?> data-spa="true">Servizi</a>
			<a href="/contatti.php" <?php echo ($currentPage ?? '') === 'contatti' ? 'class="active"' : ''; ?> data-spa="true">Contatti</a>
		</nav>

		<?php if (isLoggedIn()): ?>
		<div class="dashboard-navbar__userarea">
			<button type="button" class="dashboard-navbar__user-trigger" data-profile-modal-open aria-haspopup="dialog" aria-controls="profile-modal" aria-expanded="false">
				<div class="dashboard-navbar__user-avatar" data-profile-avatar aria-hidden="true">
					<?php if ($avatarUrl !== ''): ?><img src="<?php echo $avatarUrl; ?>" alt=""><?php else: ?><span class="material-symbols-outlined">person</span><?php endif; ?>
				</div>
				<span class="dashboard-navbar__user">Ciao, <?php echo $username; ?></span>
			</button>
			<form class="dashboard-navbar__logout" action="/auth/process-logout.php" method="post">
				<?php echo csrfInput(); ?>
				<button type="submit" class="dashboard-navbar__logout-btn" aria-label="Logout">
					<span class="material-symbols-outlined" aria-hidden="true">logout</span>
				</button>
			</form>
		</div>
		<?php else: ?>
		<div class="dashboard-navbar__userarea">
			<a href="/auth/register.php" class="auth-btn auth-btn--signup">Sign up</a>
			<a href="/auth/login.php" class="auth-btn auth-btn--signin">Sign in</a>
		</div>
		<?php endif; ?>
	</header>
	<div class="dashboard-navbar__backdrop" data-mobile-nav-backdrop hidden></div>

	<aside class="dashboard-navbar__drawer" id="mobile-nav-drawer" aria-label="Menu mobile" aria-hidden="true">
		<div class="dashboard-navbar__drawer-top">
			<a href="<?php echo isLoggedIn() ? '/' : '/?no-overlay=1'; ?>" class="dashboard-navbar__logo" aria-label="StartupFlow home"><img class="dashboard-navbar__logo-image" src="/assets/img/startupflow-mark.png" alt="StartUpFlow"></a>
			<button type="button" class="dashboard-navbar__drawer-close" data-mobile-nav-close aria-label="Chiudi menu">
				<span class="material-symbols-outlined" aria-hidden="true">close</span>
			</button>
		</div>
		<div class="dashboard-navbar__drawer-content">
			<div class="dashboard-navbar__drawer-section">
				<nav class="dashboard-navbar__drawer-links" aria-label="Static pages mobile">
					<a href="/chi-siamo.php" data-spa="true">Chi siamo</a>
					<a href="/servizi.php" data-spa="true">Servizi</a>
					<a href="/contatti.php" data-spa="true">Contatti</a>
				</nav>
			</div>

			<div class="dashboard-navbar__drawer-separator" aria-hidden="true"></div>
			<?php if (isLoggedIn()): ?>
			<div class="dashboard-navbar__drawer-section">
				<nav class="dashboard-navbar__drawer-modules" aria-label="Moduli mobile">
					<a href="/fatturazione" data-spa="true">Fatturazione</a>
					<a href="/scadenzario" data-spa="true">Scadenzario</a>
					<a href="/nota-spese" data-spa="true">Nota Spese</a>
					<a href="/burn-rate-meter" data-spa="true">Burn Rate Meter</a>
					<a href="/time-tracker" data-spa="true">Time-Tracker</a>
					<a href="/zero-carta" data-spa="true">"Zero carta"</a>
					<a href="/crm-clienti" data-spa="true">CRM clienti</a>
				</nav>
			</div>
			<?php endif; ?>
		</div>

		<?php if (isLoggedIn()): ?>
		<div class="dashboard-navbar__drawer-separator" aria-hidden="true"></div>
		<div class="dashboard-navbar__drawer-actions">
			<button type="button" class="dashboard-navbar__drawer-profile" data-profile-modal-open aria-haspopup="dialog" aria-controls="profile-modal" aria-expanded="false" aria-label="Apri profilo utente">
				<span class="dashboard-navbar__drawer-user">Ciao, <?php echo $username; ?></span>
				<div class="dashboard-navbar__user-avatar" data-profile-avatar aria-hidden="true">
					<?php if ($avatarUrl !== ''): ?><img src="<?php echo $avatarUrl; ?>" alt=""><?php else: ?><span class="material-symbols-outlined">person</span><?php endif; ?>
				</div>
			</button>
			<form class="dashboard-navbar__drawer-logout" action="/auth/process-logout.php" method="post">
				<?php echo csrfInput(); ?>
				<button type="submit" class="dashboard-navbar__logout-btn" aria-label="Logout">
					<span class="material-symbols-outlined" aria-hidden="true">logout</span>
				</button>
			</form>
		</div>
		<?php endif; ?>
	</aside>
