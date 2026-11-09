<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/auth.php';
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

try {
    // Avviamo una transazione per gestire il contatore in modo sicuro e atomico
    $pdo->beginTransaction();

    $currentYear = (int) date('Y');
    $series = 'FT'; // Prefisso della serie fatture conforme agli standard

    // Selezioniamo e blocchiamo la riga del contatore per l'anno corrente dalla nuova tabella
    $stmt = $pdo->prepare('SELECT last_number FROM invoice_counters WHERE series = :series AND year = :year FOR UPDATE');
    $stmt->execute([':series' => $series, ':year' => $currentYear]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        // Se non esiste ancora un contatore per quest'anno, lo inizializziamo a 0
        $insert = $pdo->prepare('INSERT INTO invoice_counters (series, year, last_number) VALUES (:series, :year, 0)');
        $insert->execute([':series' => $series, ':year' => $currentYear]);
        $nextNumber = 1;
    } else {
        $nextNumber = (int) $row['last_number'] + 1;
    }

    // Aggiorniamo il contatore nel database con il nuovo numero progressivo
    $update = $pdo->prepare('UPDATE invoice_counters SET last_number = :next_number WHERE series = :series AND year = :year');
    $update->execute([':next_number' => $nextNumber, ':series' => $series, ':year' => $currentYear]);

    // Formato conforme: es. FT-2026-0001 (rispetta i vincoli dello SdI)
    $documentNumber = sprintf('%s-%d-%04d', $series, $currentYear, $nextNumber);

    $storageDirectory = dirname(__DIR__) . '/storage/invoices';
    if (is_file($storageDirectory . '/' . $documentNumber . '.xml')) {
        throw new RuntimeException('Il file XML per questo numero documento esiste già');
    }

    // Confermiamo la transazione sul database
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'document_number' => $documentNumber,
    ]);
} catch (Throwable $exception) {
    // In caso di errore annulliamo la transazione
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Impossibile generare un numero progressivo valido: ' . $exception->getMessage()]);
}