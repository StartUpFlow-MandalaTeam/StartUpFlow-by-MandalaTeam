<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Non autenticato');
}

$userId = currentUserId();
$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$format = strtolower(trim((string) (filter_input(INPUT_GET, 'format', FILTER_DEFAULT) ?? 'xml')));

if (!$invoiceId || !in_array($format, ['xml', 'pdf'], true)) {
    http_response_code(400);
    exit('Parametri non validi');
}

try {
    $stmt = $pdo->prepare(
        'SELECT i.*, c.client_type, c.company_name, c.first_name, c.last_name, 
                c.address, c.city, c.postal_code, c.state, c.country, c.cf, c.vat_number, c.pec, c.sdi_code
         FROM invoices i
         INNER JOIN clients c ON c.id = i.client_id
         WHERE i.id = :id AND i.user_id = :user_id LIMIT 1'
    );
    $stmt->execute([':id' => $invoiceId, ':user_id' => $userId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        exit('Fattura non trovata');
    }

    $receivedAmount = 0.0;
    if (!empty($invoice['order_id'])) {
        $paymentStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE order_id = :order_id'
        );
        $paymentStmt->execute([':order_id' => (int) $invoice['order_id']]);
        $receivedAmount = (float) $paymentStmt->fetchColumn();
    }

    // Caricamento profilo utente (Cedente)
    $supplierStmt = $pdo->prepare('SELECT * FROM users_profiles WHERE user_id = :user_id LIMIT 1');
    $supplierStmt->execute([':user_id' => $userId]);
    $supplierProfile = $supplierStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $documentNumber = (string) $invoice['document_number'];
    $storageDirectory = __DIR__ . '/../storage/invoices';
    $xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';

    if ($format === 'xml') {
        if (!is_file($xmlPath)) {
            http_response_code(404);
            exit('File XML non trovato nello storage');
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $documentNumber . '.xml"');
        header('Content-Length: ' . (string) filesize($xmlPath));
        readfile($xmlPath);
        exit;
    }

    if ($format === 'pdf') {
        $items = [];
        $causale = '';
        $cassaAmount = 0.0;
        $cassaRate = 0.0;
        $expensesAmount = 0.0;
        $nonTaxableExpensesAmount = 0.0;
        $cassaTaxRate = 0.0;
        $ritenutaAmount = 0.0;
        $ritenutaRate = 0.0;
        $ritenutaType = '';
        $virtualDuty = '';
        $vatNotes = [];
        $subtotal = 0.0;
        $isSimplified = false;
        $paymentTerms = '';
        $paymentMethod = '';
        $paymentAmount = null;
        $paymentStatusLabel = '';
        $xmlVatSummary = [];
        $paymentMethodLabels = [];
        $cassaNames = [];
        $cassaJson = @file_get_contents(__DIR__ . '/../assets/data/casse-previdenziali.json');
        $cassaCatalog = $cassaJson === false ? null : json_decode($cassaJson, true);
        if (is_array($cassaCatalog)) {
            foreach ($cassaCatalog as $cassaEntry) {
                if (isset($cassaEntry['value'], $cassaEntry['name'])) {
                    $cassaNames[(string) $cassaEntry['value']] = (string) $cassaEntry['name'];
                }
            }
        }
        $cassaType = '';
        $paymentCatalog = json_decode((string) @file_get_contents(__DIR__ . '/../assets/data/modalita-pagamento.json'), true);
        if (is_array($paymentCatalog)) {
            foreach ($paymentCatalog as $paymentEntry) {
                if (isset($paymentEntry['value'], $paymentEntry['name'])) {
                    $paymentMethodLabels[(string) $paymentEntry['value']] = (string) $paymentEntry['name'];
                }
            }
        }
        if (is_file($xmlPath)) {
            $documentXml = new DOMDocument();
            if (@$documentXml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $documentTypeNode = $documentXml->getElementsByTagName('TipoDocumento')->item(0);
                $isSimplified = $documentTypeNode instanceof DOMElement
                    && trim($documentTypeNode->textContent) === 'TD07';
                $paymentInfoNode = $documentXml->getElementsByTagName('DatiPagamento')->item(0);
                if ($paymentInfoNode instanceof DOMElement) {
                    $termsNode = $paymentInfoNode->getElementsByTagName('CondizioniPagamento')->item(0);
                    $methodNode = $paymentInfoNode->getElementsByTagName('ModalitaPagamento')->item(0);
                    $amountNode = $paymentInfoNode->getElementsByTagName('ImportoPagamento')->item(0);
                    $paymentTerms = $termsNode instanceof DOMElement ? trim($termsNode->textContent) : '';
                    $paymentMethod = $methodNode instanceof DOMElement ? trim($methodNode->textContent) : '';
                    $paymentAmount = max(0, (float) $invoice['total'] - $receivedAmount);
                    if ($paymentAmount <= 0 || !empty($invoice['is_paid'])) {
                        $paymentStatusLabel = 'Pagamento effettuato';
                    } elseif ($receivedAmount > 0) {
                        $paymentStatusLabel = 'Pagamento parziale';
                    } else {
                        $paymentStatusLabel = 'Pagamento da effettuare';
                    }
                }
            }
            try {
                $eDoc = DigitalDocument::parseFrom($xmlPath);
                $instances = $eDoc->getDocumentInstances();
                if (!empty($instances)) {
                    $instance = $instances[0];
                    $descriptions = $instance->getDescriptions();
                    $causale = implode("\n", $descriptions);

                    $virtualDuty = $instance->getVirtualDuty();

                    foreach ($instance->getTotals() as $summaryTotal) {
                        $nature = $summaryTotal->getVatNature();
                        $natureCode = $nature !== null ? (string) $nature : '';
                        $summaryRate = (float) ($summaryTotal->getTaxPercentage() ?? 0);
                        $summaryKey = number_format($summaryRate, 2, '.', '');
                        if ($natureCode !== 'N1') {
                            $xmlVatSummary[$summaryKey] = [
                                'rate' => $summaryRate,
                                'taxable' => (float) ($summaryTotal->getTotal() ?? 0),
                                'tax' => (float) ($summaryTotal->getTaxAmount() ?? 0),
                            ];
                        }
                        if (method_exists($summaryTotal, 'getOtherExpenses') && $summaryTotal->getOtherExpenses() !== null) {
                            if ($natureCode === 'N1') {
                                $nonTaxableExpensesAmount += (float) $summaryTotal->getOtherExpenses();
                            } else {
                                $expensesAmount += (float) $summaryTotal->getOtherExpenses();
                            }
                        }
                        if ($nature !== null) {
                            $vatNotes[] = [
                                'nature' => $natureCode,
                                'reference' => (string) ($summaryTotal->getReference() ?? ''),
                            ];
                        }
                    }

                    $funds = $instance->getFunds();
                    if (!empty($funds)) {
                        $cassaAmount = (float) $funds[0]->getAmount();
                        $cassaRate = (float) ($funds[0]->getPercentage() ?? 0);
                        $cassaTaxRate = (float) ($funds[0]->getTaxPercentage() ?? 0);
                        $cassaType = (string) $funds[0]->getType();
                    }
                    if ($instance->getDeductionType() !== null) {
                        $ritenutaType = (string) $instance->getDeductionType();
                        $ritenutaRate = (float) ($instance->getDeductionPercentage() ?? 0);
                        $ritenutaAmount = (float) ($instance->getDeductionAmount() ?? 0);
                    }

                    if ($isSimplified) {
                        foreach ($instance->getSimplifiedLines() as $line) {
                            $lineTaxable = (float)$line->getTotal();
                            $lineNature = method_exists($line, 'getVatNature') && $line->getVatNature() !== null
                                ? (string) $line->getVatNature()
                                : '';
                            $lineReference = method_exists($line, 'getReference')
                                ? trim((string) ($line->getReference() ?? ''))
                                : '';
                            if ($lineNature !== '') {
                                $vatNotes[] = [
                                    'nature' => $lineNature,
                                    'reference' => $lineReference,
                                ];
                            }
                            if ($lineNature === 'N1') {
                                $nonTaxableExpensesAmount += $lineTaxable;
                                continue;
                            }
                            $subtotal += $lineTaxable;
                            $items[] = [
                                'Description' => $line->getDescription(),
                                'Quantity' => '1',
                                'UnitPrice' => number_format($lineTaxable, 2, '.', ''),
                                'Discount' => '',
                                'VatRate' => (string)($line->getTaxPercentage() ?? 0),
                                'LineTotal' => number_format($lineTaxable, 2, '.', ''),
                                'TaxAmount' => number_format((float) ($line->getTaxAmount() ?? 0), 2, '.', ''),
                                'TaxableAmount' => $lineTaxable,
                            ];
                        }
                    } else {
                        foreach ($instance->getLines() as $line) {
                            $lineTaxable = (float)$line->getTotal();
                            $subtotal += $lineTaxable;
                            $discountLabel = '';
                            foreach ($line->getDiscounts() as $discount) {
                                $percentage = $discount->getPercentage();
                                $amount = $discount->getAmount();
                                if ($amount !== null && (float) $amount > 0) {
                                    $discountLabel = number_format((float) $amount, 2, '.', '') . ' ' . $invoice['currency'];
                                } elseif ($percentage !== null && (float) $percentage > 0) {
                                    $discountLabel = number_format((float) $percentage, 2, '.', '') . '%';
                                }
                                break;
                            }
                            $items[] = [
                                'Description' => $line->getDescription(),
                                'Quantity' => number_format((float)$line->getQuantity(), 2, '.', ''),
                                'UnitPrice' => number_format((float)$line->getUnitPrice(), 2, '.', ''),
                                'Discount' => $discountLabel,
                                'VatRate' => (string)($line->getTaxPercentage() ?? 0),
                                'LineTotal' => number_format(round($lineTaxable * (1 + (float)($line->getTaxPercentage() ?? 0) / 100), 2), 2, '.', ''),
                                'TaxAmount' => number_format(round($lineTaxable * (float)($line->getTaxPercentage() ?? 0) / 100, 2), 2, '.', ''),
                                'TaxableAmount' => $lineTaxable,
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fallback if parsing fails
            }

            if (empty($items)) {
                $xml = new DOMDocument();
                if (@$xml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
                    $causaleNode = $xml->getElementsByTagName('Causale')->item(0);
                    if ($causaleNode instanceof DOMElement && $causale === '') {
                        $causale = $causaleNode->textContent;
                    }
                    foreach ($xml->getElementsByTagName('SpeseAccessorie') as $speseNode) {
                        if ($speseNode instanceof DOMElement) {
                            $summaryNode = $speseNode->parentNode;
                            $natureNode = $summaryNode instanceof DOMElement
                                ? $summaryNode->getElementsByTagName('Natura')->item(0)
                                : null;
                            if ($natureNode instanceof DOMElement && $natureNode->textContent === 'N1') {
                                $nonTaxableExpensesAmount += (float) $speseNode->textContent;
                            } else {
                                $expensesAmount += (float) $speseNode->textContent;
                            }
                        }
                    }
                    $itemFields = ['Description', 'Quantity', 'UnitPrice', 'VatRate', 'Nature', 'TaxableAmount', 'TaxAmount', 'AdditionalExpenses', 'LegalReference', 'LineTotal'];
                    foreach ($xml->getElementsByTagName('Item') as $itemNode) {
                        $item = [];
                        $discountNode = $itemNode->getElementsByTagName('ScontoMaggiorazione')->item(0);
                        foreach ($itemNode->childNodes as $fieldNode) {
                            if ($fieldNode instanceof DOMElement && in_array($fieldNode->tagName, $itemFields, true)) {
                                $item[$fieldNode->tagName] = $fieldNode->textContent;
                            }
                        }
                        if (!empty($item)) {
                            $discountLabel = '';
                            if ($discountNode instanceof DOMElement) {
                                $discountTypeNode = $discountNode->getElementsByTagName('Tipo')->item(0);
                                $discountValueNode = $discountNode->getElementsByTagName('Percentuale')->item(0);
                                if (!$discountValueNode instanceof DOMElement) {
                                    $discountValueNode = $discountNode->getElementsByTagName('Importo')->item(0);
                                }
                                if ($discountValueNode instanceof DOMElement) {
                                    $discountLabel = trim($discountValueNode->textContent)
                                        . ($discountTypeNode instanceof DOMElement && trim($discountTypeNode->textContent) === 'SC'
                                            && $discountNode->getElementsByTagName('Percentuale')->length > 0 ? '%' : ' ' . $invoice['currency']);
                                }
                            }
                            $items[] = [
                                'Description' => $item['Description'] ?? '',
                                'Quantity' => $item['Quantity'] ?? '1',
                                'UnitPrice' => $item['UnitPrice'] ?? '0',
                                'Discount' => $discountLabel,
                                'VatRate' => $item['VatRate'] ?? '0',
                                'LineTotal' => $item['LineTotal'] ?? '0',
                                'TaxAmount' => $item['TaxAmount'] ?? '0',
                                'TaxableAmount' => (float)($item['TaxableAmount'] ?? ($item['LineTotal'] ?? 0)),
                            ];
                        }
                    }
                }
            }

            if ($subtotal === 0.0 && !empty($items)) {
                foreach ($items as $item) {
                    $subtotal += (float)($item['TaxableAmount'] ?? ((float)($item['Quantity'] ?? 1) * (float)($item['UnitPrice'] ?? 0)));
                }
            }
        }

        $vatByRate = $xmlVatSummary;
        if (empty($vatByRate)) {
            foreach ($items as $item) {
                $rate = (float) ($item['VatRate'] ?? 0);
                $lineTotal = (float) ($item['LineTotal'] ?? 0);
                $tax = (float) ($item['TaxAmount'] ?? 0);
                $taxable = $isSimplified ? max(0, $lineTotal - $tax) : (float) ($item['TaxableAmount'] ?? 0);
                if ($tax <= 0 && $rate > 0) {
                    $tax = round($taxable * $rate / 100, 2);
                }
                $rateKey = number_format($rate, 2, '.', '');
                if (!isset($vatByRate[$rateKey])) {
                    $vatByRate[$rateKey] = [
                        'rate' => $rate,
                        'taxable' => 0.0,
                        'tax' => 0.0,
                    ];
                }
                $vatByRate[$rateKey]['taxable'] += $taxable;
                $vatByRate[$rateKey]['tax'] += $tax;
            }
        }
        if (empty($xmlVatSummary) && $cassaAmount > 0 && $cassaTaxRate > 0 && !empty($vatByRate)) {
            $totalTaxable = array_sum(array_column($vatByRate, 'taxable'));
            if ($totalTaxable > 0) {
                $allocatedCassa = 0.0;
                $lastRateKey = array_key_last($vatByRate);
                foreach ($vatByRate as $rateKey => &$vatSummary) {
                    $cassaShare = $rateKey === $lastRateKey
                        ? round($cassaAmount - $allocatedCassa, 2)
                        : round($cassaAmount * $vatSummary['taxable'] / $totalTaxable, 2);
                    $allocatedCassa += $cassaShare;
                    $vatSummary['taxable'] += $cassaShare;
                    $vatSummary['tax'] += round($cassaShare * $vatSummary['rate'] / 100, 2);
                }
                unset($vatSummary);
            }
        }
        ksort($vatByRate, SORT_NUMERIC);
        $vatTaxableTotal = array_sum(array_column($vatByRate, 'taxable'));
        $totalVat = array_sum(array_column($vatByRate, 'tax'));
        $vatNotes = array_values(array_unique(array_map(
            static fn (array $note): string => $note['nature'] . '|' . $note['reference'],
            $vatNotes
        )));
        $vatNotes = array_map(
            static function (string $note): array {
                [$nature, $reference] = explode('|', $note, 2);
                return ['nature' => $nature, 'reference' => $reference];
            },
            $vatNotes
        );

        $clientName = $invoice['client_type'] === 'company'
            ? $invoice['company_name']
            : trim($invoice['first_name'] . ' ' . $invoice['last_name']);

        $supplierName = !empty($supplierProfile['company_name'])
            ? $supplierProfile['company_name']
            : trim(($supplierProfile['first_name'] ?? '') . ' ' . ($supplierProfile['last_name'] ?? ''));

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <title>Fattura <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></title>
            <style>
                @page { size: A4; margin: 12mm; }
                body { font-family: system-ui, -apple-system, sans-serif; color: #111; line-height: 1.35; margin: 1rem; font-size: 0.88rem; }
                h1, h2, h3 { margin: 0 0 0.35rem; }
                .header { display: flex; justify-content: space-between; margin-bottom: 1rem; border-bottom: 2px solid #eee; padding-bottom: 0.6rem; }
                .header p { margin: 0.2rem 0; font-size: 0.78rem; }
                .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem; border-bottom: 2px solid #eee; padding-bottom: 0.8rem; page-break-inside: avoid; }
                .parties h3 { font-size: 0.82rem; }
                .parties p { margin: 0; font-size: 0.74rem; line-height: 1.25; }
                .causale { margin-bottom: 0.8rem; page-break-inside: avoid; }
                .causale h3 { font-size: 0.82rem; }
                .causale p { margin: 0; font-size: 0.74rem; line-height: 1.25; white-space: normal; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; page-break-inside: auto; }
                tr { page-break-inside: avoid; }
                th, td { padding: 0.42rem 0.5rem; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.78rem; }
                th { background: #f8f9fa; font-weight: 600; }
                .totals { margin-left: auto; width: 280px; page-break-inside: avoid; }
                .totals div { display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px solid #eee; font-size: 0.8rem; }
                .totals div.vat-breakdown { font-size: 0.7rem; }
                .totals div.total { font-weight: bold; font-size: 0.95rem; border-top: 2px solid #333; border-bottom: none; margin-top: 0.35rem; padding-top: 0.35rem; }
                .print-bar { background: #111; color: #fff; padding: 0.7rem; text-align: center; margin-bottom: 1rem; border-radius: 8px; }
                .print-bar button { background: #fff; color: #111; border: none; padding: 0.4rem 0.8rem; font-weight: bold; cursor: pointer; border-radius: 4px; }
                .document-type { display: inline-block; margin-left: 0.75rem; padding: 0.2rem 0.6rem; border: 1px solid #333; border-radius: 999px; font-size: 0.75rem; text-transform: uppercase; }
                @media print { .print-bar { display: none; } body { margin: 0; font-size: 0.82rem; } }
            </style>
        </head>
        <body>
            <div class="print-bar">
                <span>Visualizzazione Fattura <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                <button onclick="window.print()">Salva come PDF / Stampa</button>
            </div>

            <div class="header">
                <div>
                    <h1>Fattura <?php echo $isSimplified ? 'semplificata' : 'ordinaria'; ?>
                        <span class="document-type"><?php echo $isSimplified ? 'TD07' : 'TD01'; ?></span>
                    </h1>
                    <div style="display: flex; flex-direction: row; gap: 1rem;">
                        <p><strong>N. Documento:</strong> <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Data:</strong> <?php echo htmlspecialchars(!empty($invoice['issue_date']) ? date('d-m-Y', strtotime($invoice['issue_date'])) : '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Termine di Pagamento:</strong> <?php echo htmlspecialchars(!empty($invoice['due_date']) ? date('d-m-Y', strtotime($invoice['due_date'])) : '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <?php if ($paymentMethod !== '' || $paymentTerms !== ''): ?>
                        <p style="font-size: 0.74rem; margin: 0.2rem 0;">
                            <strong>Pagamento:</strong>
                            <?php echo htmlspecialchars([
                                'TP01' => 'TP01 - a rate',
                                'TP02' => 'TP02 - completo',
                                'TP03' => 'TP03 - anticipato',
                            ][$paymentTerms] ?? $paymentTerms, ENT_QUOTES, 'UTF-8'); ?>
                            | modalità: <?php echo htmlspecialchars($paymentMethodLabels[$paymentMethod] ?? $paymentMethod, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($paymentAmount !== null): ?>
                                | residuo: <?php echo number_format($paymentAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                            | status: <?php echo htmlspecialchars($paymentStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="parties">
                <div>
                    <h3>Cedente (Prestatore):</h3>
                    <p><strong><?php echo htmlspecialchars($supplierName !== '' ? $supplierName : 'Cedente', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <?php if (!empty($supplierProfile['address'])): ?>
                        <?php echo htmlspecialchars($supplierProfile['address'] . (!empty($supplierProfile['street_number']) ? ' ' . $supplierProfile['street_number'] : ''), ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars(($supplierProfile['postal_code'] ?? '') . ' ' . ($supplierProfile['city'] ?? '') . ' (' . ($supplierProfile['province'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($supplierProfile['cf'])): ?>C.F.: <?php echo htmlspecialchars($supplierProfile['cf'], ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
                    <?php if (!empty($supplierProfile['vat_number'])): ?>P.IVA: <?php echo htmlspecialchars($supplierProfile['vat_number'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </p>
                </div>
                <div>
                    <h3>Cessionario (Cliente):</h3>
                    <p><strong><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <?php if (!empty($invoice['address'])): ?>
                        <?php echo htmlspecialchars($invoice['address'], ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars($invoice['postal_code'] . ' ' . $invoice['city'] . ' (' . ($invoice['state'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($invoice['cf'])): ?>C.F.: <?php echo htmlspecialchars($invoice['cf'], ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
                    <?php if (!empty($invoice['vat_number'])): ?>P.IVA: <?php echo htmlspecialchars($invoice['vat_number'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($causale)): ?>
                <div class="causale">
                    <h3>Causale</h3>
                    <p><?php echo nl2br(htmlspecialchars($causale, ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($vatNotes)): ?>
                <div style="margin-bottom: 0.2rem; font-size: 0.7rem;">
                    <?php foreach ($vatNotes as $note): ?>
                        <p><strong>Natura IVA <?php echo htmlspecialchars($note['nature'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                            <?php echo htmlspecialchars($note['reference'] !== '' ? $note['reference'] : 'Operazione non soggetta ad IVA', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($virtualDuty === 'SI' || (float)$invoice['bollo'] > 0): ?>
                <div style="margin-bottom: 1rem; font-size: 0.7rem;">
                    <p><strong>Imposta di bollo assolta in modo virtuale ai sensi dell'art. 15 del D.P.R. 642/1972 e del D.M. 17/06/2014</strong></p>
                    <?php if (!empty($invoice['bollo_to_client']) && (float) $invoice['bollo'] > 0): ?>
                        <p>Importo bollo addebitato al cliente: <strong><?php echo number_format((float) $invoice['bollo'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($cassaAmount > 0 && !empty($cassaType)): ?>
                    <div style="margin-bottom: 1rem; font-size: 0.7rem;">
                        <p><strong>Cassa previdenziale:</strong> <?php echo htmlspecialchars($cassaType, ENT_QUOTES, 'UTF-8'); ?> – <?php echo htmlspecialchars($cassaNames[$cassaType] ?? $cassaType, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endif; ?>

                <p style="font-size: 0.7rem;">
                    L'invio della copia analogica (PDF) della fattura elettronica è un servizio di cortesia.
                    L’originale della fattura elettronica è disponibile nell’area riservata del sito web
                    Fatture e Corrispettivi dell’Agenzia delle entrate e costituisce l’unico documento
                    affidabile ai fini fiscali. Invitiamo l'utente a verificare il contenuto della copia
                    analogica al fine di escludere il rischio di eventuali inesattezze, imprecisioni e/o
                    manomissioni di terzi. Documento trasmesso tramite Sistema di Interscambio (SdI).
                </p>

                <table>
                <thead>
                    <tr>
                        <th>Descrizione</th>
                        <th>Q.tà</th>
                        <th>P.U.</th>
                        <th>Sconto</th>
                        <th>IVA %</th>
                        <th>Imposta IVA</th>
                        <th>Totale Riga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['Description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['Quantity'] ?? '1', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($item['UnitPrice'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['Discount'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['VatRate'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>%</td>
                            <td><?php echo number_format((float) ($item['TaxAmount'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($item['LineTotal'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <div>
                    <span>Totale servizi/merci:</span>
                    <span><?php echo number_format($subtotal, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($cassaAmount > 0): ?>
                    <div>
                        <span>
                            Rivalsa INPS/Cassa
                        </span>
                        <span><?php echo number_format($cassaAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($expensesAmount > 0): ?>
                    <div>
                        <span>Spese accessorie imponibili:</span>
                        <span><?php echo number_format($expensesAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <div>
                    <span>Imponibile IVA:</span>
                    <span><?php echo number_format($vatTaxableTotal, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php foreach ($vatByRate as $vatSummary): ?>
                    <div class="vat-breakdown">
                        <span>Imposta IVA <?php echo number_format($vatSummary['rate'], 0, ',', '.'); ?>%:</span>
                        <span><?php echo number_format($vatSummary['tax'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
                <div>
                    <span>Totale imposta IVA:</span>
                    <span><?php echo number_format($totalVat, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($ritenutaType !== ''): ?>
                    <div>
                        <span>Ritenuta d'acconto (<?php echo number_format($ritenutaRate, 0, ',', '.'); ?>%):</span>
                        <span>- <?php echo number_format($ritenutaAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($nonTaxableExpensesAmount > 0): ?>
                    <div>
                        <span>Spese escluse ex art. 15:</span>
                        <span><?php echo number_format($nonTaxableExpensesAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['bollo_to_client']) && (float) $invoice['bollo'] > 0): ?>
                    <div>
                        <span>Imposta di bollo:</span>
                        <span><?php echo number_format((float) $invoice['bollo'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total">
                    <span><?php echo $ritenutaType !== '' ? 'Totale a pagare:' : 'Totale Fattura:'; ?></span>
                    <span><?php echo number_format((float) $invoice['total'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Errore durante il download: ' . $exception->getMessage());
}