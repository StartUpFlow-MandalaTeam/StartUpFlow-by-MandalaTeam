<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/invoice-integration.php';
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore di avvio del servizio: ' . $exception->getMessage()]);
    exit;
}

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

// Tipo documento: '' / AUTO / TD01 / TD07 / TD16 = fatture di vendita e TD16 (sezionale FT);
// TD17 / TD18 / TD19 = integrazioni di acquisti dall'estero (sezionale dedicato INT, numerazione distinta).
$documentType = strtoupper(trim((string) ($_POST['document_type'] ?? 'AUTO')));
if (!in_array($documentType, ['', 'AUTO', 'TD01', 'TD07', 'TD16'], true) && !integrationIsType($documentType)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tipo documento non valido']);
    exit;
}

// La numerazione riparte da 1 a ogni anno solare della DATA DOCUMENTO (se non indicata, l'anno corrente).
$issueDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) ($_POST['issue_date'] ?? '')));
$year = $issueDate !== false ? (int) $issueDate->format('Y') : (int) date('Y');
$series = invoiceSeriesFor($documentType);

try {
    // Il numero viene solo PROPOSTO: il contatore avanza al salvataggio della fattura (process-invoice.php).
    // Così aprire o cambiare il modulo, o abbandonarlo, non lascia salti nella numerazione.
    $stmt = $pdo->prepare('SELECT last_number FROM invoice_counters WHERE series = :series AND year = :year');
    $stmt->execute([':series' => $series, ':year' => $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNumber = $row === false ? 1 : (int) $row['last_number'] + 1;

    // Formato conforme: es. FT-2026-0001, INT-2026-0001 (rispetta i vincoli dello SdI)
    $documentNumber = sprintf('%s-%d-%04d', $series, $year, $nextNumber);

    $storageDirectory = dirname(__DIR__) . '/storage/invoices';
    if (is_file($storageDirectory . '/' . $documentNumber . '.xml')) {
        throw new RuntimeException('Il file XML per questo numero documento esiste già');
    }

    echo json_encode([
        'success' => true,
        'document_number' => $documentNumber,
    ]);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Impossibile generare un numero progressivo valido: ' . $exception->getMessage()]);
}