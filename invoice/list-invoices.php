<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profile-fiscal.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;

header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $data): void {
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	$respond(405, ['success' => false, 'message' => 'Metodo non consentito']);
}

if (!isLoggedIn()) {
	$respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

$userId = currentUserId();
if ($userId === null) {
	$respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

try {
	$statement = $pdo->prepare(
		' SELECT i.id, i.document_number, i.issue_date, i.due_date, i.currency,
		        i.tax_regime, i.total, i.bollo, i.bollo_to_client, i.is_paid, c.client_type, c.company_name,
		        c.first_name, c.last_name
		 FROM invoices i
		 INNER JOIN clients c ON c.id = i.client_id
		 WHERE i.user_id = :user_id
		 ORDER BY i.issue_date DESC, i.id DESC'
	);
	$statement->execute([':user_id' => $userId]);
	$invoices = [];

	$cassaNames = [];
	$cassaJson = @file_get_contents(dirname(__DIR__) . '/assets/data/casse-previdenziali.json');
	$cassaCatalog = $cassaJson === false ? null : json_decode($cassaJson, true);
	if (is_array($cassaCatalog)) {
		foreach ($cassaCatalog as $cassaEntry) {
			if (isset($cassaEntry['value'], $cassaEntry['name'])) {
				$cassaNames[(string) $cassaEntry['value']] = (string) $cassaEntry['name'];
			}
		}
	}

	$storageDirectory = dirname(__DIR__) . '/storage/invoices';

	foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $invoice) {
		$clientName = $invoice['client_type'] === 'company'
			? $invoice['company_name']
			: trim($invoice['first_name'] . ' ' . $invoice['last_name']);
		$invoice['client_name'] = $clientName !== '' ? $clientName : 'Cliente';
		$invoice['is_paid'] = (bool) $invoice['is_paid'];
		$invoice['bollo_to_client'] = (bool) $invoice['bollo_to_client'];
		$invoice['tax_regime_label'] = fiscalRegimeLabel((string) $invoice['tax_regime']);
		
		// Salvaguardia formato data in gg-mm-aaaa per l'output se utile
		if (!empty($invoice['issue_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice['issue_date'])) {
			$invoice['issue_date_formatted'] = date('d-m-Y', strtotime($invoice['issue_date']));
		}
		if (!empty($invoice['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice['due_date'])) {
			$invoice['due_date_formatted'] = date('d-m-Y', strtotime($invoice['due_date']));
		}

		$invoice['items'] = [];
		$invoice['causale'] = '';
		$invoice['xml_available'] = false;
		$invoice['cassa'] = null;
		$documentNumber = (string) $invoice['document_number'];

		if (preg_match('/^[A-Z]+-\d{4}-\d+$/', $documentNumber)) {
			$xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
			$fileSize = is_file($xmlPath) ? filesize($xmlPath) : false;
			if ($fileSize !== false && $fileSize <= 2_000_000) {
				try {
					$eDoc = DigitalDocument::parseFrom($xmlPath);
					$invoice['xml_available'] = true;
					$instances = $eDoc->getDocumentInstances();
					if (!empty($instances)) {
						$instance = $instances[0];
						$descriptions = $instance->getDescriptions();
						$invoice['causale'] = implode("\n", $descriptions);

						$funds = $instance->getFunds();
						if (!empty($funds)) {
							$fund = $funds[0];
							$cassaType = (string) $fund->getType();
							$cassa = [
								'type' => $cassaType,
								'rate' => number_format((float) $fund->getPercentage(), 2, '.', ''),
								'amount' => number_format((float) $fund->getAmount(), 2, '.', ''),
								'taxable_amount' => number_format((float) ($fund->getSubtotal() ?? 0), 2, '.', ''),
								'vat_rate' => number_format((float) ($fund->getTaxPercentage() ?? 0), 2, '.', ''),
							];
							if ($cassaType !== '') {
								$cassa['type_label'] = $cassaNames[$cassaType] ?? $cassaType;
							}
							if (isset($cassa['amount'], $cassa['vat_rate'])) {
								$cassa['tax_amount'] = number_format(round((float) $cassa['amount'] * (float) $cassa['vat_rate'] / 100, 2), 2, '.', '');
							}
							$invoice['cassa'] = $cassa;
						}

						if ($eDoc->isSimplified()) {
							foreach ($instance->getSimplifiedLines() as $line) {
								$invoice['items'][] = [
									'description' => $line->getDescription(),
									'quantity' => '1',
									'unit_price' => number_format((float)$line->getTotal(), 2, '.', ''),
									'vat_rate' => $line->getTaxPercentage() ?? 0,
									'nature' => $line->getVatNature() ? (string)$line->getVatNature() : '',
									'taxable_amount' => number_format((float)$line->getTotal(), 2, '.', ''),
									'tax_amount' => number_format((float)($line->getTaxAmount() ?? 0), 2, '.', ''),
									'additional_expenses' => '0.00',
									'legal_reference' => $line->getReference() ?? '',
									'line_total' => number_format((float)$line->getTotal(), 2, '.', ''),
								];
							}
						} else {
							$summaryReferences = [];
								foreach ($instance->getTotals() as $summaryTotal) {
									if ($summaryTotal->getVatNature() !== null && $summaryTotal->getReference() !== null) {
										$summaryReferences[(string) $summaryTotal->getVatNature()] = (string) $summaryTotal->getReference();
									}
								}
								foreach ($instance->getLines() as $line) {
								$invoice['items'][] = [
									'description' => $line->getDescription(),
									'quantity' => number_format((float)$line->getQuantity(), 2, '.', ''),
									'unit_price' => number_format((float)$line->getUnitPrice(), 2, '.', ''),
									'vat_rate' => $line->getTaxPercentage() ?? 0,
									'nature' => $line->getVatNature() ? (string)$line->getVatNature() : '',
									'taxable_amount' => number_format((float)$line->getTotal(), 2, '.', ''),
									'tax_amount' => number_format(round((float)$line->getTotal() * (float)($line->getTaxPercentage() ?? 0) / 100, 2), 2, '.', ''),
									'additional_expenses' => '0.00',
									'legal_reference' => $summaryReferences[(string) $line->getVatNature()] ?? '',
									'line_total' => number_format(round((float)$line->getTotal() * (1 + (float)($line->getTaxPercentage() ?? 0) / 100), 2), 2, '.', ''),
								];
							}
						}
					}
				} catch (Throwable $e) {
					// Fallback to DOMDocument parsing for legacy XML files
				}

				if (empty($invoice['items'])) {
					$xml = new DOMDocument();
					if (@$xml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
						$invoice['xml_available'] = true;
						$causaleNode = $xml->getElementsByTagName('Causale')->item(0);
						if ($causaleNode instanceof DOMElement) {
							$invoice['causale'] = $causaleNode->textContent;
						}
						$itemFields = [
							'Description' => 'description',
							'Quantity' => 'quantity',
							'UnitPrice' => 'unit_price',
							'VatRate' => 'vat_rate',
							'Nature' => 'nature',
							'TaxableAmount' => 'taxable_amount',
							'TaxAmount' => 'tax_amount',
							'AdditionalExpenses' => 'additional_expenses',
							'LegalReference' => 'legal_reference',
							'LineTotal' => 'line_total',
						];
						foreach ($xml->getElementsByTagName('Item') as $itemNode) {
							$item = [];
							foreach ($itemNode->childNodes as $fieldNode) {
								if ($fieldNode instanceof DOMElement && isset($itemFields[$fieldNode->tagName])) {
									$item[$itemFields[$fieldNode->tagName]] = $fieldNode->textContent;
								}
							}
							$invoice['items'][] = $item;
						}
					}
				}
			}
		}

		unset($invoice['client_type'], $invoice['company_name'], $invoice['first_name'], $invoice['last_name']);
		$invoices[] = $invoice;
	}

	$respond(200, ['success' => true, 'invoices' => $invoices]);
} catch (Throwable $exception) {
	error_log('Invoice list failed: ' . $exception->getMessage());
	$respond(500, ['success' => false, 'message' => 'Errore durante il caricamento delle fatture']);
}
