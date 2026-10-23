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

$userId = currentUserId();
$clientId = currentSelectedClientId();
if ($userId === null || $clientId === null) {
	$respond(400, ['success' => false, 'message' => 'Seleziona un cliente prima di salvare la fattura']);
}

$documentNumber = trim((string) ($_POST['document_number'] ?? ''));
$issueDate = (string) ($_POST['issue_date'] ?? '');
$dueDate = (string) ($_POST['due_date'] ?? '');
$causale = trim((string) ($_POST['causale'] ?? ''));
$currency = strtoupper(trim((string) ($_POST['divisa'] ?? '')));
$regimeInput = (string) ($_POST['regime_fiscale'] ?? '');
$taxRegime = [
	'regime forfettario' => 'forfettario',
	'regime ordinario' => 'ordinario',
][$regimeInput] ?? null;

$isValidDate = static function (string $date): bool {
	$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
	$errors = DateTimeImmutable::getLastErrors();
	return $parsedDate !== false
		&& ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
		&& $parsedDate->format('Y-m-d') === $date;
};

if (!preg_match('/^SF-[A-F0-9]{16}$/', $documentNumber)) {
	$respond(422, ['success' => false, 'message' => 'Numero documento non valido']);
}
if (!$isValidDate($issueDate) || !$isValidDate($dueDate) || $dueDate < $issueDate) {
	$respond(422, ['success' => false, 'message' => 'Controlla le date del documento e della scadenza']);
}
if ($taxRegime === null) {
	$respond(422, ['success' => false, 'message' => 'Regime fiscale non valido']);
}
if (strlen($causale) > 8000) {
	$respond(422, ['success' => false, 'message' => 'La causale supera la lunghezza consentita']);
}

$descriptions = $_POST['descrizione'] ?? null;
$quantities = $_POST['quantita'] ?? null;
$unitPrices = $_POST['prezzo_unitario'] ?? null;
$vatRates = $_POST['aliquota_iva'] ?? null;
$natures = $_POST['natura'] ?? null;
$expenses = $_POST['spese_accessorie'] ?? null;
$legalReferences = $_POST['riferimento_normativo'] ?? null;
$lineArrays = [$descriptions, $quantities, $unitPrices, $vatRates, $natures, $expenses, $legalReferences];
if (count(array_filter($lineArrays, 'is_array')) !== count($lineArrays)) {
	$respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi non validi']);
}

$lineCount = count($descriptions);
foreach ($lineArrays as $values) {
	if (count($values) !== $lineCount) {
		$respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi incompleti']);
	}
}
if ($lineCount === 0) {
	$respond(422, ['success' => false, 'message' => 'Aggiungi almeno un bene o servizio']);
}

$allowedRates = $taxRegime === 'forfettario' ? [0] : [4, 5, 10, 22];
$allowedNatures = ['non_soggetta', 'esente', 'fuori_campo'];
$lineItems = [];
$invoiceTotal = 0.0;

foreach ($descriptions as $index => $rawDescription) {
	$description = trim((string) $rawDescription);
	$quantity = filter_var((string) $quantities[$index], FILTER_VALIDATE_INT);
	$unitPriceRaw = (string) $unitPrices[$index];
	$rate = filter_var((string) $vatRates[$index], FILTER_VALIDATE_INT);
	$nature = (string) $natures[$index];
	$expenseRaw = (string) $expenses[$index];
	$legalReference = trim((string) $legalReferences[$index]);

	if ($description === '' || strlen($description) > 8000 || strlen($legalReference) > 2000 || $quantity === false || $quantity < 0
		|| !is_numeric($unitPriceRaw) || !is_finite((float) $unitPriceRaw) || (float) $unitPriceRaw < 0
		|| $rate === false || !in_array($rate, $allowedRates, true)
		|| !in_array($nature, $allowedNatures, true)
		|| !is_numeric($expenseRaw) || !is_finite((float) $expenseRaw) || (float) $expenseRaw < 0
		|| ($taxRegime === 'forfettario' && $nature !== 'non_soggetta')) {
		$respond(422, ['success' => false, 'message' => 'Controlla i dati dei beni, delle aliquote e del riepilogo']);
	}

	$unitPrice = round((float) $unitPriceRaw, 2);
	$additionalExpenses = round((float) $expenseRaw, 2);
	$taxableAmount = round($quantity * $unitPrice, 2);
	$taxAmount = round($taxableAmount * ($rate / 100), 2);
	$lineTotal = round($taxableAmount + $taxAmount + $additionalExpenses, 2);
	$invoiceTotal = round($invoiceTotal + $lineTotal, 2);

	$lineItems[] = [
		'description' => $description,
		'quantity' => $quantity,
		'unit_price' => number_format($unitPrice, 2, '.', ''),
		'vat_rate' => $rate,
		'nature' => $nature,
		'taxable_amount' => number_format($taxableAmount, 2, '.', ''),
		'tax_amount' => number_format($taxAmount, 2, '.', ''),
		'additional_expenses' => number_format($additionalExpenses, 2, '.', ''),
		'legal_reference' => $legalReference,
		'line_total' => number_format($lineTotal, 2, '.', ''),
	];
}

if ($invoiceTotal > 99999999.99) {
	$respond(422, ['success' => false, 'message' => 'Il totale supera il limite consentito']);
}

try {
	$currencyJson = file_get_contents(__DIR__ . '/../assets/data/currency.json');
	$currencyList = json_decode($currencyJson === false ? '' : $currencyJson, true, 512, JSON_THROW_ON_ERROR);
	$validCurrencies = array_column($currencyList, 'value');
	if (!in_array($currency, $validCurrencies, true)) {
		$respond(422, ['success' => false, 'message' => 'Divisa non valida']);
	}

	$clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = :client_id AND user_id = :user_id LIMIT 1');
	$clientCheck->execute([':client_id' => $clientId, ':user_id' => $userId]);
	if (!$clientCheck->fetchColumn()) {
		$respond(403, ['success' => false, 'message' => 'Il cliente selezionato non è valido']);
	}

	$storageDirectory = dirname(__DIR__) . '/storage/invoices';
	if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
		throw new RuntimeException('Could not create invoice storage directory');
	}

	$checkNumber = $pdo->prepare('SELECT 1 FROM invoices WHERE document_number = :document_number LIMIT 1');
	$insertInvoice = $pdo->prepare('INSERT INTO invoices (client_id, user_id, document_number, issue_date, due_date, currency, tax_regime, total) VALUES (:client_id, :user_id, :document_number, :issue_date, :due_date, :currency, :tax_regime, :total)');
	$documentNumberExists = static function (string $number) use ($checkNumber, $storageDirectory): bool {
		$checkNumber->execute([':document_number' => $number]);
		return $checkNumber->fetchColumn() !== false || is_file($storageDirectory . '/' . $number . '.xml');
	};
	$generateDocumentNumber = static function () use ($checkNumber, $storageDirectory): string {
		for ($attempt = 0; $attempt < 20; $attempt++) {
			$number = 'SF-' . strtoupper(bin2hex(random_bytes(8)));
			$checkNumber->execute([':document_number' => $number]);
			if ($checkNumber->fetchColumn() === false && !is_file($storageDirectory . '/' . $number . '.xml')) {
				return $number;
			}
		}
		throw new RuntimeException('Could not generate a unique invoice number');
	};
	$buildXml = static function (string $number) use ($lineItems, $causale): string {
		$xml = new DOMDocument('1.0', 'UTF-8');
		$xml->formatOutput = true;
		$root = $xml->createElement('Invoice');
		$root->setAttribute('documentNumber', $number);
		$xml->appendChild($root);
		$causaleElement = $xml->createElement('Causale');
		$causaleElement->appendChild($xml->createTextNode($causale));
		$root->appendChild($causaleElement);
		$items = $xml->createElement('Items');
		$root->appendChild($items);

		foreach ($lineItems as $lineItem) {
			$item = $xml->createElement('Item');
			$items->appendChild($item);
			foreach ($lineItem as $name => $value) {
				$element = $xml->createElement(str_replace('_', '', ucwords($name, '_')));
				$element->appendChild($xml->createTextNode((string) $value));
				$item->appendChild($element);
			}
		}

		$document = $xml->saveXML();
		if ($document === false) {
			throw new RuntimeException('Could not serialize invoice XML');
		}
		return $document;
	};

	if ($documentNumberExists($documentNumber)) {
		$respond(409, [
			'success' => false,
			'message' => 'Il numero documento era già stato usato. È stato generato un nuovo numero; salva di nuovo.',
			'document_number' => $generateDocumentNumber(),
		]);
	}

	for ($attempt = 0; $attempt < 10; $attempt++) {
		if ($documentNumberExists($documentNumber)) {
			$documentNumber = $generateDocumentNumber();
			continue;
		}

		$xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
		$xmlContent = $buildXml($documentNumber);
		$file = @fopen($xmlPath, 'x');
		if ($file === false) {
			$documentNumber = $generateDocumentNumber();
			continue;
		}
		$written = fwrite($file, $xmlContent);
		fclose($file);
		if ($written !== strlen($xmlContent)) {
			@unlink($xmlPath);
			throw new RuntimeException('Could not write invoice XML');
		}
		@chmod($xmlPath, 0600);

		try {
			$pdo->beginTransaction();
			$insertInvoice->execute([
				':client_id' => $clientId,
				':user_id' => $userId,
				':document_number' => $documentNumber,
				':issue_date' => $issueDate,
				':due_date' => $dueDate,
				':currency' => $currency,
				':tax_regime' => $taxRegime,
				':total' => number_format($invoiceTotal, 2, '.', ''),
			]);
			$invoiceId = (int) $pdo->lastInsertId();
			$pdo->commit();

			$respond(201, [
				'success' => true,
				'invoice_id' => $invoiceId,
				'document_number' => $documentNumber,
				'xml_file' => $documentNumber . '.xml',
			]);
		} catch (PDOException $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			@unlink($xmlPath);
			$isDuplicate = (int) ($exception->errorInfo[1] ?? 0) === 1062;
			if ($isDuplicate) {
				$documentNumber = $generateDocumentNumber();
				continue;
			}
			throw $exception;
		} catch (Throwable $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			@unlink($xmlPath);
			throw $exception;
		}
	}

	$respond(503, ['success' => false, 'message' => 'Impossibile salvare la fattura con un numero univoco']);
} catch (Throwable $exception) {
	error_log('Invoice save failed: ' . $exception->getMessage());
	$respond(500, ['success' => false, 'message' => 'Errore durante il salvataggio della fattura']);
}