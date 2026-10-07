<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
	exit;
}

if (!isLoggedIn()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
	exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Richiesta non autorizzata']);
	exit;
}

try {
	$check = $pdo->prepare('SELECT 1 FROM invoices WHERE document_number = :document_number LIMIT 1');
	$storageDirectory = dirname(__DIR__) . '/storage/invoices';
	$generateDocumentNumber = static function () use ($check, $storageDirectory): string {
		for ($attempt = 0; $attempt < 20; $attempt++) {
			$number = 'SF-' . strtoupper(bin2hex(random_bytes(8)));
			$check->execute([':document_number' => $number]);
			if ($check->fetchColumn() === false && !is_file($storageDirectory . '/' . $number . '.xml')) {
				return $number;
			}
		}
		throw new RuntimeException('Could not generate a unique invoice number');
	};

	echo json_encode([
		'success' => true,
		'document_number' => $generateDocumentNumber(),
	]);
} catch (Throwable $exception) {
	http_response_code(503);
	echo json_encode(['success' => false, 'message' => 'Impossibile generare un numero documento univoco']);
}