<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'StartUpFlow';
$currentPage = '';

// Set body class
$bodyClass = 'dashboard-page';

// Add overlay CSS for non-logged-in users
$additionalCss = '<link rel="stylesheet" href="/assets/css/startup-overlay.css">';

require_once __DIR__ . '/includes/layout/header.php';

// Fetch user profile data from the database for logged in users
$username = '';
$email = '';
$createdAt = '';

if (isLoggedIn()) {
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
}

?>

<?php if (isLoggedIn()): ?>
	<main class="main">
		<div id="app">
			<div id="dynamic-content">
				<!-- Contenuto dinamico verra' caricato qui dal router SPA -->
			</div>
		</div>
	</main>
<?php else: ?>
	<main class="auth-main">
		<!-- Startup Overlay -->
		<div class="startup-overlay" id="startupOverlay" aria-hidden="true">
			<button class="startup-overlay__power" type="button" id="startupPowerBtn" aria-label="Power">
				<span style="font-size: 78px; color: #d40000; display: block;">⏻</span>
			</button>
			<div class="startup-overlay__title">StartupFlow</div>
		</div>
		
		<div class="auth-buttons" id="authButtons" hidden>
			<a href="/auth/register.php" class="auth-btn auth-btn--signup">Sign up</a>
			<a href="/auth/login.php" class="auth-btn auth-btn--signin">Sign in</a>
		</div>
	</main>
	<script src="/assets/js/startup-overlay.js"></script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/layout/footer.php';
?>
<?php if (isLoggedIn()): ?>
	<script src="/assets/js/spa-router.js"></script>
<?php endif; ?>
