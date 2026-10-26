<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $data): void {
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$respond(405, ['success' => false, 'message' => 'Metodo non consentito']);
}

if (!isLoggedIn()) {
	$respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
	$respond(403, ['success' => false, 'message' => 'Richiesta non autorizzata']);
}

$action = $_POST['action'] ?? '';
if ($action === 'start') {
	$_SESSION['invoice_timer_started_at'] = microtime(true);
	$_SESSION['invoice_timer_id'] = bin2hex(random_bytes(16));
	$respond(200, [
		'success' => true,
		'timer_id' => $_SESSION['invoice_timer_id'],
	]);
}

if ($action === 'cancel') {
	$timerId = $_POST['timer_id'] ?? '';
	if (
		is_string($timerId)
		&& isset($_SESSION['invoice_timer_id'])
		&& hash_equals((string) $_SESSION['invoice_timer_id'], $timerId)
	) {
		unset($_SESSION['invoice_timer_started_at'], $_SESSION['invoice_timer_id']);
	}
	$respond(200, ['success' => true]);
}

$respond(422, ['success' => false, 'message' => 'Azione timer non valida']);
