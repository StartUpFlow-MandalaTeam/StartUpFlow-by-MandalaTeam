<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile-fiscal.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
	exit;
}

global $pdo;
$userId = currentUserId();
$fields = [
	'client_type', 'company_name', 'first_name', 'last_name', 'vat_number',
	'cf', 'address', 'city', 'state', 'postal_code', 'country',
	'email', 'phone', 
	//'crm_status',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$action = $_GET['action'] ?? '';
	if ($action === 'orders') {
		$clientId = (int) ($_GET['client_id'] ?? 0);
		if ($clientId <= 0) {
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Cliente non valido']);
			exit;
		}
		$stmt = $pdo->prepare(
			'SELECT o.id, o.order_number, o.total, o.currency, o.notes, o.status, o.payment_status,
			        COALESCE((SELECT SUM(oi.quantity * oi.unit_price - oi.discount_amount) FROM order_items oi WHERE oi.order_id = o.id), 0) AS total_without_vat,
			        o.paid_at, o.created_at,
			        COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id = o.id), 0) AS paid_amount,
			        (SELECT p.method FROM payments p WHERE p.order_id = o.id ORDER BY p.received_at DESC, p.id DESC LIMIT 1) AS last_payment_method,
			        (SELECT p.reference FROM payments p WHERE p.order_id = o.id ORDER BY p.received_at DESC, p.id DESC LIMIT 1) AS last_payment_reference,
			        (SELECT p.received_at FROM payments p WHERE p.order_id = o.id ORDER BY p.received_at DESC, p.id DESC LIMIT 1) AS last_payment_date
			 FROM orders o
			 WHERE o.client_id = ? AND o.user_id = ?
			 ORDER BY o.created_at DESC'
		);
		$stmt->execute([$clientId, $userId]);
		$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($orders) {
			$ids = array_map(static fn($o) => (int) $o['id'], $orders);
			$in = implode(',', array_fill(0, count($ids), '?'));
			$itemsStmt = $pdo->prepare("SELECT id, order_id, position, description, quantity, unit_price, discount_type, discount_value, discount_amount, vat_rate, line_total FROM order_items WHERE order_id IN ($in) ORDER BY order_id, position, id");
			$itemsStmt->execute($ids);
			$byOrder = [];
			foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
				$byOrder[(int) $item['order_id']][] = $item;
			}
			foreach ($orders as &$order) {
				$order['items'] = $byOrder[(int) $order['id']] ?? [];
			}
		}
		echo json_encode(['success' => true, 'orders' => $orders]);
		exit;
	}

	$stmt = $pdo->prepare('SELECT id, client_type, company_name, first_name, last_name, vat_number, cf, address, city, state, postal_code, country, email, phone, crm_status FROM clients WHERE user_id = :user_id ORDER BY company_name, last_name, first_name');
	$stmt->execute([':user_id' => $userId]);
	echo json_encode(['success' => true, 'clients' => $stmt->fetchAll()]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'select_client') {
	if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Richiesta non autorizzata']);
		exit;
	}

	$clientId = (int) ($_POST['client_id'] ?? 0);
	if ($clientId <= 0) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Cliente non valido']);
		exit;
	}

	$stmt = $pdo->prepare('SELECT id, client_type, company_name, first_name, last_name, vat_number, cf, address, city, state, postal_code, country, email, phone, crm_status FROM clients WHERE id = :id AND user_id = :user_id LIMIT 1');
	$stmt->execute([':id' => $clientId, ':user_id' => $userId]);
	$client = $stmt->fetch();

	if (!$client) {
		http_response_code(404);
		echo json_encode(['success' => false, 'message' => 'Cliente non trovato']);
		exit;
	}

	$_SESSION['selected_client_id'] = $clientId;
	$_SESSION['selected_client'] = $client;

	echo json_encode(['success' => true, 'client_id' => $clientId, 'message' => 'Cliente selezionato']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Richiesta non autorizzata']);
	exit;
}

$client = [];
foreach ($fields as $field) {
	$client[$field] = trim((string) ($_POST[$field] ?? ''));
}

// Convert stringhe vuote in NULL per i campi facoltativi (coerenza con crm-clienti.php)
// Questo permette a più clienti dello stesso utente di avere vat_number = NULL
// senza violare l'UNIQUE KEY uk_clients_user_vat (user_id, vat_number)
$optionalFields = ['vat_number', 'cf', 'pec', 'sdi_code', 'company_name', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'state', 'postal_code', 'country', 'source', 'notes'];
foreach ($optionalFields as $field) {
    if ($client[$field] === '') {
        $client[$field] = null;
    }
}

$client['client_type'] = in_array($client['client_type'], ['individual', 'company'], true)
	? $client['client_type']
	: 'company';
// $client['crm_status'] = in_array($client['crm_status'], ['lead', 'customer', ''], true)
// 	? $client['crm_status']
// 	: 'lead';

$requiredClientFields = ['address', 'city', 'state', 'postal_code', 'country', 'email', 'phone'];
if ($client['client_type'] === 'company') {
	$requiredClientFields[] = 'company_name';
} else {
	$requiredClientFields[] = 'first_name';
	$requiredClientFields[] = 'last_name';
	$requiredClientFields[] = 'cf';
}

foreach ($requiredClientFields as $requiredField) {
	if ($client[$requiredField] === '') {
		echo json_encode(['success' => false, 'message' => 'Compila tutti i campi obbligatori del cliente']);
		exit;
	}
}

// Validate email format if provided
if ($client['email'] !== '' && !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
	echo json_encode(['success' => false, 'message' => 'Email non valida']);
	exit;
}

// Validate VAT number format if provided
if ($client['vat_number'] !== null && !fiscalIsValidVat($client['vat_number'])) {
		echo json_encode(['success' => false, 'message' => 'Partita IVA non valida (deve essere 11 cifre con cifra di controllo corretta)']);
		exit;
	}		

$params = array_combine(
	array_map(static fn (string $field): string => ':' . $field, $fields),
	array_values($client)
);
$id = (int) ($_POST['id'] ?? 0);

try {
	if ($id > 0) {
		$sql = 'UPDATE clients SET ' . implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, $fields)) . ' WHERE id = :id AND user_id = :user_id';
		$stmt = $pdo->prepare($sql);
		$params[':id'] = $id;
		$params[':user_id'] = $userId;
		$stmt->execute($params);
	} else {
		$sql = 'INSERT INTO clients (user_id, ' . implode(', ', $fields) . ') VALUES (:user_id, ' . implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields)) . ')';
		$stmt = $pdo->prepare($sql);
		$params[':user_id'] = $userId;
		$stmt->execute($params);
		$id = (int) $pdo->lastInsertId();
	}

	$_SESSION['selected_client_id'] = $id;
	$_SESSION['selected_client'] = $client + ['id' => $id];

	echo json_encode(['success' => true, 'id' => $id, 'message' => 'Cliente salvato']);
} catch (Throwable $exception) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Impossibile salvare il cliente']);
}
