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

// Array delle card con titolo, descrizione e link
$cards = [
	['title' => 'Fatturazione', 'description' => 'Emetti le tue fatture', 'link' => '/fatturazione'],
	['title' => 'Scadenzario', 'description' => 'Pagamenti & Incassi', 'link' => '/scadenzario'],
	['title' => 'Nota Spese', 'description' => 'Registra le uscite', 'link' => '/nota-spese'],
	['title' => 'Burn Rate Meter', 'description' => 'Il tuo stato di salute', 'link' => '/burn-rate-meter'],
	['title' => 'Time-Tracker', 'description' => 'Tempo di fatturazione per cliente', 'link' => '/time-tracker'],
	['title' => '"Zero carta"', 'description' => 'Repository documentale', 'link' => '/zero-carta'],
	['title' => 'CRM clienti', 'description' => 'Ciclo di acquisizione clienti', 'link' => '/crm-clienti'],
];

$pageTitle = 'Dashboard';
$currentPage = '';

require_once __DIR__ . '/includes/layout/header.php';

?>

	<main class="main">
		<div class="card-container">
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
	</main>
	
<?php
require_once __DIR__ . '/includes/layout/footer.php';
