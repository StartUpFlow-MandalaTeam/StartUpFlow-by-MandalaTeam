<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'StartUpFlow';
$currentPage = '';

// Set body class
$bodyClass = 'dashboard-page';

// Check if overlay should be hidden (when coming from navbar click)
$hideOverlay = isset($_GET['no-overlay']) && $_GET['no-overlay'] === '1';

// Add overlay CSS for non-logged-in users
$additionalCss = '<link rel="stylesheet" href="/assets/css/startup-overlay.css">';

require_once __DIR__ . '/includes/layout/header.php';

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
		<div class="startup-overlay" id="startupOverlay" aria-hidden="true" <?php echo $hideOverlay ? 'style="display: none !important;"' : ''; ?>>
			<button class="startup-overlay__power" type="button" id="startupPowerBtn" aria-label="Power">
				<span style="font-size: 78px; color: #d40000; display: block;">⏻</span>
			</button>
			<div class="startup-overlay__title" onclick="window.location.href='/'" style="cursor: pointer;">StartupFlow</div>
		</div>
		
		<div class="auth-buttons" id="authButtons" <?php echo !$hideOverlay ? 'hidden' : ''; ?>>
			<a href="/auth/register.php" class="auth-btn auth-btn--signup">Sign up</a>
			<a href="/auth/login.php" class="auth-btn auth-btn--signin">Sign in</a>
		</div>
		<script src="/assets/js/startup-overlay.js"></script>
		<script>
			// Se c'è il parametro no-overlay, puliamo l'URL per permettere il refresh
			if (window.location.search.includes('no-overlay=1')) {
				history.replaceState(null, '', window.location.pathname);
			}
		</script>
	</main>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/layout/footer.php';
?>
<?php if (isLoggedIn()): ?>
	<script src="/assets/js/zero-carta.js"></script>
	<script src="/assets/js/spa-router.js"></script>
<?php endif; ?>
