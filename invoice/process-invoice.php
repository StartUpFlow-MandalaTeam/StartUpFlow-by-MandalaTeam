<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/profile-fiscal.php';
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Throwable $exception) {
    respond(500, ['success' => false, 'message' => 'Errore di avvio del servizio: ' . $exception->getMessage()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Metodo non consentito']);
}

if (!isLoggedIn()) {
    respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    respond(403, ['success' => false, 'message' => 'Richiesta non autorizzata']);
}

$userId = currentUserId();
$clientId = currentSelectedClientId();
if ($userId === null || $clientId === null) {
    respond(400, ['success' => false, 'message' => 'Seleziona un cliente prima di salvare la fattura']);
}

use FatturaElettronicaPhp\FatturaElettronica\Address;
use FatturaElettronicaPhp\FatturaElettronica\Customer;
use FatturaElettronicaPhp\FatturaElettronica\Deduction;
use FatturaElettronicaPhp\FatturaElettronica\Discount;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocumentInstance;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DocumentType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DeductionType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DiscountType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\PaymentMethod;
use FatturaElettronicaPhp\FatturaElettronica\Enums\PaymentTerm;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TaxRegime;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TransmissionFormat;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatEligibility;
use FatturaElettronicaPhp\FatturaElettronica\Line;
use FatturaElettronicaPhp\FatturaElettronica\SimplifiedLine;
use FatturaElettronicaPhp\FatturaElettronica\Fund;
use FatturaElettronicaPhp\FatturaElettronica\PaymentDetails;
use FatturaElettronicaPhp\FatturaElettronica\PaymentInfo;
use FatturaElettronicaPhp\FatturaElettronica\Supplier;
use FatturaElettronicaPhp\FatturaElettronica\Total;

/**
 * Catalogo Natura IVA (assets/data/natura.json) indicizzato per codice.
 */
function loadNatureCatalog(): array {
    $jsonPath = dirname(__DIR__) . '/assets/data/natura.json';
    $data = is_file($jsonPath) ? json_decode((string) file_get_contents($jsonPath), true) : null;
    if (!is_array($data)) {
        throw new RuntimeException('Catalogo Natura IVA non disponibile');
    }
    $catalog = [];
    foreach ($data as $entry) {
        if (isset($entry['value'])) {
            $catalog[(string) $entry['value']] = $entry;
        }
    }
    return $catalog;
}

// Caricamento profilo utente (Cedente)
$stmt = $pdo->prepare('SELECT * FROM users_profiles WHERE user_id = :user_id LIMIT 1');
$stmt->execute([':user_id' => $userId]);
$supplierProfile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplierProfile || empty($supplierProfile['vat_number']) || empty($supplierProfile['cf'])) {
    respond(422, ['success' => false, 'message' => 'Profilo fiscale incompleto. Configura Partita IVA e Codice Fiscale nelle impostazioni.']);
}

// Caricamento cliente (Cessionario)
$clientStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :client_id AND user_id = :user_id LIMIT 1');
$clientStmt->execute([':client_id' => $clientId, ':user_id' => $userId]);
$client = $clientStmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    respond(403, ['success' => false, 'message' => 'Il cliente selezionato non è valido']);
}

$orderId = (int) ($_POST['order_id'] ?? 0);
if ($orderId > 0) {
    $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id AND client_id = :client_id AND user_id = :user_id LIMIT 1');
    $orderStmt->execute([':id' => $orderId, ':client_id' => $clientId, ':user_id' => $userId]);
    if (!$orderStmt->fetchColumn()) {
        respond(422, ['success' => false, 'message' => 'Ordine selezionato non valido']);
    }
    $orderItemsStmt = $pdo->prepare(
        'SELECT description, quantity, unit_price, discount_type, discount_value, vat_rate
         FROM order_items WHERE order_id = :order_id ORDER BY position, id'
    );
    $orderItemsStmt->execute([':order_id' => $orderId]);
    $orderItems = $orderItemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$orderItems) {
        respond(422, ['success' => false, 'message' => 'L’ordine selezionato non contiene righe']);
    }
    $_POST['descrizione'] = array_column($orderItems, 'description');
    $_POST['quantita'] = array_column($orderItems, 'quantity');
    $_POST['prezzo_unitario'] = array_column($orderItems, 'unit_price');
    $_POST['tipo_sconto'] = array_column($orderItems, 'discount_type');
    $_POST['valore_sconto'] = array_column($orderItems, 'discount_value');
    $_POST['aliquota_iva'] = array_column($orderItems, 'vat_rate');
}

// Controllo esclusione fatture verso PA (Codice destinatario di 6 caratteri o formato FPA12)
$sdiCode = trim((string) ($client['sdi_code'] ?? ''));
if (strlen($sdiCode) === 6 || strtolower((string) ($_POST['invoice_format'] ?? '')) === 'pa') {
    respond(422, ['success' => false, 'message' => 'Le fatture verso la Pubblica Amministrazione (PA) sono escluse e non supportate da questo sistema.']);
}

$documentNumber = trim((string) ($_POST['document_number'] ?? ''));
$causale = trim((string) ($_POST['causale'] ?? ''));
$currency = strtoupper(trim((string) ($_POST['divisa'] ?? 'EUR')));
if ($currency === '') {
    $currency = 'EUR';
}

// Normalizzazione e salvaguardia del formato date (gg-mm-aaaa o yyyy-mm-dd)
function normalizeDate(string $date): string {
    $date = trim($date);
    if ($date === '') {
        return '';
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $date;
}

$issueDateRaw = (string) ($_POST['issue_date'] ?? '');
$dueDateRaw = (string) ($_POST['due_date'] ?? '');
$issueDate = normalizeDate($issueDateRaw);
$dueDate = normalizeDate($dueDateRaw);

$paymentStatus = strtolower(trim((string) ($_POST['payment_status'] ?? 'unpaid')));
$paymentTermsCode = strtoupper(trim((string) ($_POST['payment_terms'] ?? 'TP02')));
$paymentMethodCode = strtoupper(trim((string) ($_POST['payment_method'] ?? 'MP05')));
$paymentAmount = round((float) ($_POST['payment_amount'] ?? 0), 2);
$paymentReference = trim((string) ($_POST['payment_reference'] ?? ''));
$validPaymentStatuses = ['unpaid', 'partial', 'paid'];
$validPaymentTerms = ['TP01', 'TP02', 'TP03'];
$validPaymentMethods = array_map(
    static fn (array $entry): string => (string) ($entry['value'] ?? ''),
    json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/data/modalita-pagamento.json'), true) ?: []
);
if (!in_array($paymentStatus, $validPaymentStatuses, true)
    || !in_array($paymentTermsCode, $validPaymentTerms, true)
    || !in_array($paymentMethodCode, $validPaymentMethods, true)
    || strlen($paymentReference) > 60
    || $paymentAmount < 0
    || $paymentAmount > 1000000000
) {
    respond(422, ['success' => false, 'message' => 'Dati di pagamento non validi']);
}

// Regime fiscale del cedente: catalogo regimi-fiscali.json. Nessun valore di ripiego: un regime assente,
// sconosciuto o non gestito blocca l'emissione invece di essere trattato in silenzio come ordinario.
$taxRegimeCode = strtoupper(trim((string) ($supplierProfile['tax_regime'] ?? '')));
$regimeInfo = fiscalRegime($taxRegimeCode);
if ($regimeInfo === null) {
    respond(422, ['success' => false, 'message' => 'Regime fiscale del cedente mancante o non valido. Selezionalo nel profilo.']);
}
if (!$regimeInfo['supported']) {
    respond(422, ['success' => false, 'message' => 'Il regime fiscale ' . $taxRegimeCode . ' non è ancora gestito per l\'emissione delle fatture.']);
}
$appliesVat = $regimeInfo['applies_vat'];              // false: aliquota sempre 0
$fixedNature = $regimeInfo['fixed_nature'];            // Natura imposta quando l'IVA non si applica
$defaultReference = $regimeInfo['default_reference'];  // dicitura di legge predefinita
$enumTaxRegime = new TaxRegime($taxRegimeCode);

$bolloToClient = (int) ($_POST['bollo_to_client'] ?? 0);
$bolloAmount = 0.0;

// Cassa previdenziale
$cassaTipo = strtoupper(trim((string) ($_POST['cassa_tipo'] ?? '')));
$cassaAliquota = (float) ($_POST['cassa_aliquota'] ?? 0);
$cassaImponibile = (float) ($_POST['cassa_imponibile'] ?? 0);
$cassaAliquotaIva = (int) ($_POST['cassa_aliquota_iva'] ?? 0);
$cassaNatura = trim((string) ($_POST['cassa_natura'] ?? ''));
$cassaImporto = (float) ($_POST['cassa_importo'] ?? 0);
$cassaImposta = (float) ($_POST['cassa_imposta'] ?? 0);
$cassaRiferimento = trim((string) ($_POST['cassa_riferimento'] ?? ''));
$ritenutaTipo = strtoupper(trim((string) ($_POST['ritenuta_tipo'] ?? '')));
$ritenutaAliquota = (float) ($_POST['ritenuta_aliquota'] ?? 0);

$ritenuteCatalogData = json_decode(
    (string) file_get_contents(dirname(__DIR__) . '/assets/data/ritenute.json'),
    true
);
$ritenuteCatalog = [];
if (is_array($ritenuteCatalogData)) {
    foreach ($ritenuteCatalogData as $entry) {
        if (isset($entry['value'], $entry['name'])) {
            $ritenuteCatalog[(string) $entry['value']] = (string) $entry['name'];
        }
    }
}
if ($ritenutaTipo !== '' && !isset($ritenuteCatalog[$ritenutaTipo])) {
    respond(422, ['success' => false, 'message' => 'Tipo di ritenuta non valido']);
}
if ($ritenutaAliquota < 0 || $ritenutaAliquota > 100) {
    respond(422, ['success' => false, 'message' => 'Aliquota ritenuta non valida']);
}

function isValidDate(string $date): bool {
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    return $parsedDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date;
}

function normalizeCountry(?string $country): string {
    $c = trim((string) $country);
    if ($c === '') {
        return 'IT';
    }
    $cUpper = strtoupper($c);
    if (strlen($cUpper) === 2) {
        return $cUpper;
    }
    
    static $countryMap = null;
    if ($countryMap === null) {
        $countryMap = [];
        $jsonPath = dirname(__DIR__) . '/assets/data/countries.json';
        if (is_file($jsonPath)) {
            $data = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($data)) {
                foreach ($data as $entry) {
                    if (isset($entry['code'], $entry['name'])) {
                        $countryMap[strtoupper($entry['name'])] = strtoupper($entry['code']);
                    }
                }
            }
        }
        $countryMap['ITALIA'] = 'IT';
        $countryMap['ITALY'] = 'IT';
    }

    return $countryMap[$cUpper] ?? 'IT';
}

if (!preg_match('/^[A-Z]+-\d{4}-\d+$/', $documentNumber)) {
    respond(422, ['success' => false, 'message' => 'Numero documento non valido']);
}
if (!isValidDate($issueDate) || !isValidDate($dueDate) || $dueDate < $issueDate) {
    respond(422, ['success' => false, 'message' => 'Controlla le date del documento e della scadenza']);
}
if (strlen($causale) > 8000) {
    respond(422, ['success' => false, 'message' => 'La causale supera la lunghezza consentita']);
}

$descriptions = $_POST['descrizione'] ?? null;
$quantities = $_POST['quantita'] ?? null;
$unitPrices = $_POST['prezzo_unitario'] ?? null;
$discountTypes = $_POST['tipo_sconto'] ?? null;
$discountValues = $_POST['valore_sconto'] ?? null;
$vatRates = $_POST['aliquota_iva'] ?? null;
$natures = $_POST['natura'] ?? null;
$expenses = $_POST['spese_accessorie'] ?? null;
$nonTaxableExpenses = $_POST['spese_accessorie_non_imponibili'] ?? null;
$legalReferences = $_POST['riferimento_normativo'] ?? null;

if (!is_array($descriptions) || !is_array($quantities) || !is_array($unitPrices) || !is_array($vatRates)) {
    respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi non validi']);
}

$lineCount = count($descriptions);
if (!is_array($discountTypes) || count($discountTypes) !== $lineCount) {
    $discountTypes = array_fill(0, $lineCount, 'none');
}
if (!is_array($discountValues) || count($discountValues) !== $lineCount) {
    $discountValues = array_fill(0, $lineCount, '0');
}
$natures = is_array($natures) && count($natures) === $lineCount
    ? $natures
    : array_fill(0, $lineCount, '');
$expenses = is_array($expenses) && count($expenses) === $lineCount
    ? $expenses
    : array_fill(0, $lineCount, '0');
$legalReferences = is_array($legalReferences) && count($legalReferences) === $lineCount
    ? $legalReferences
    : array_fill(0, $lineCount, '');
$lineArrays = [$descriptions, $quantities, $unitPrices, $discountTypes, $discountValues, $vatRates, $natures, $expenses, $legalReferences];
foreach ($lineArrays as $values) {
    if (!is_array($values) || count($values) !== $lineCount) {
        respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi incompleti']);
    }
}
if ($lineCount === 0) {
    respond(422, ['success' => false, 'message' => 'Aggiungi almeno un bene o servizio']);
}
if (!is_array($nonTaxableExpenses) || count($nonTaxableExpenses) !== $lineCount) {
    $nonTaxableExpenses = array_fill(0, $lineCount, '0');
}

try {
    $natureCatalog = loadNatureCatalog();
    $allowedRates = $appliesVat ? [0, 4, 5, 10, 22] : [0];

    $lineItems = [];
    $invoiceTotal = 0.0;
    $taxableSum = 0.0;
    $expensesSum = 0.0;
    $nonTaxableExpensesSum = 0.0;

    foreach ($descriptions as $index => $rawDescription) {
        $quantityRaw = (string) $quantities[$index];
        $unitPriceRaw = (string) $unitPrices[$index];
        $discountType = trim((string) $discountTypes[$index]);
        $discountValueRaw = (string) $discountValues[$index];
        $rateInput = trim((string) $vatRates[$index]);
        $rate = is_numeric($rateInput) && is_finite((float) $rateInput)
            && (float) $rateInput >= 0
            && (float) $rateInput === (float) ((int) $rateInput)
            ? (int) $rateInput
            : false;
        $nature = trim((string) $natures[$index]);
        $expenseRaw = (string) $expenses[$index];
        $nonTaxableExpenseRaw = (string) $nonTaxableExpenses[$index];
        $legalReference = trim((string) $legalReferences[$index]);

        $invalidField = null;
        if ($rawDescription === '' || strlen($rawDescription) > 8000) {
            $invalidField = 'descrizione';
        } elseif (!is_numeric($quantityRaw) || !is_finite((float) $quantityRaw) || (float) $quantityRaw < 0) {
            $invalidField = 'quantità';
        } elseif (!is_numeric($unitPriceRaw) || !is_finite((float) $unitPriceRaw) || (float) $unitPriceRaw < 0) {
            $invalidField = 'prezzo unitario';
        } elseif (!in_array($discountType, ['none', 'percentage', 'amount'], true)
            || !is_numeric($discountValueRaw) || !is_finite((float) $discountValueRaw) || (float) $discountValueRaw < 0) {
            $invalidField = 'sconto';
        } elseif ($rate === false) {
            $invalidField = 'aliquota IVA';
        } elseif (!is_numeric($expenseRaw) || !is_finite((float) $expenseRaw) || (float) $expenseRaw < 0) {
            $invalidField = 'spese accessorie';
        } elseif (!is_numeric($nonTaxableExpenseRaw) || !is_finite((float) $nonTaxableExpenseRaw) || (float) $nonTaxableExpenseRaw < 0) {
            $invalidField = 'spese escluse';
        }
        if ($invalidField !== null) {
            respond(422, ['success' => false, 'message' => 'Controlla il campo ' . $invalidField . ' della riga ' . ($index + 1)]);
        }

        if (mb_strlen($legalReference) > 100) {
            respond(422, ['success' => false, 'message' => 'Il riferimento normativo non può superare i 100 caratteri']);
        }
        if (!$appliesVat) {
            // Il regime fiscale del cedente prevale sull'aliquota eventualmente
            // memorizzata nell'ordine: nel regime forfettario l'IVA è sempre 0%.
            $rate = 0;
        } elseif (!in_array($rate, $allowedRates, true)) {
            respond(422, ['success' => false, 'message' => !$appliesVat
                ? 'Nel regime ' . $taxRegimeCode . ' l\'aliquota IVA deve essere 0%'
                : 'Aliquota IVA non valida']);
        }

        // Natura: obbligatoria con aliquota 0, da omettere con aliquota > 0 (altrimenti lo SdI scarta il file).
        if ($rate > 0) {
            $nature = null;
        } elseif (!$appliesVat) {
            $nature = $fixedNature;
            if ($legalReference === '' && $defaultReference !== null) {
                $legalReference = $defaultReference;
            }
        } elseif (isset($natureCatalog[$nature])) {
            // codice valido scelto dall'utente
        } else {
            respond(422, ['success' => false, 'message' => 'Seleziona una Natura IVA valida per le righe con aliquota 0%']);
        }

        $quantity = round((float) $quantityRaw, 2);
        $unitPrice = round((float) $unitPriceRaw, 2);
        $grossAmount = round($quantity * $unitPrice, 2);
        $discountValue = round((float) $discountValueRaw, 2);
        if ($discountType === 'percentage' && $discountValue > 100) {
            respond(422, ['success' => false, 'message' => 'Lo sconto percentuale non può superare il 100%']);
        }
        if ($discountType === 'percentage') {
            $discountAmount = round($grossAmount * $discountValue / 100, 2);
        } elseif ($discountType === 'amount') {
            $discountAmount = $discountValue;
        } else {
            $discountAmount = 0.0;
        }
        if ($discountAmount > $grossAmount) {
            respond(422, ['success' => false, 'message' => 'Lo sconto non può superare il valore della riga']);
        }
        $additionalExpenses = round((float) $expenseRaw, 2);
        $nonTaxableAdditionalExpenses = round((float) $nonTaxableExpenseRaw, 2);
        $taxableAmount = round($grossAmount - $discountAmount, 2);
        $taxAmount = round(($taxableAmount + $additionalExpenses) * ($rate / 100), 2);
        $lineTotal = round($taxableAmount + $taxAmount + $additionalExpenses, 2);
        $invoiceTotal = round($invoiceTotal + $lineTotal, 2);
        $taxableSum = round($taxableSum + $taxableAmount, 2);
        $expensesSum = round($expensesSum + $additionalExpenses, 2);
        $nonTaxableExpensesSum = round($nonTaxableExpensesSum + $nonTaxableAdditionalExpenses, 2);

        $lineItems[] = [
            'description' => $rawDescription,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'vat_rate' => $rate,
            'nature' => $nature,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'additional_expenses' => $additionalExpenses,
            'non_taxable_additional_expenses' => $nonTaxableAdditionalExpenses,
            'legal_reference' => $legalReference,
            'line_total' => $lineTotal,
        ];
    }

    $ritenutaAmount = 0.0;
    if ($ritenutaTipo !== '' && $regimeInfo['withholding']) {
        $ritenutaAmount = round($taxableSum * $ritenutaAliquota / 100, 2);
    } else {
        $ritenutaTipo = '';
        $ritenutaAliquota = 0.0;
    }

    // Cassa: le regole dipendono dal regime; con aliquota 0 serve la Natura (i campi disabilitati non arrivano nel POST).
    if ($cassaTipo !== '') {
        if (!$appliesVat) {
            $cassaAliquotaIva = 0;
        }
        if ($cassaAliquotaIva > 0) {
            $cassaNatura = '';
        } elseif (!$appliesVat) {
            $cassaNatura = $fixedNature;
        } elseif (!isset($natureCatalog[$cassaNatura])) {
            respond(422, ['success' => false, 'message' => 'Seleziona una Natura IVA valida per il contributo cassa con aliquota 0%']);
        }
    }

    // Calcolo cassa previdenziale
    $cassaCalculatedAmount = 0.0;
    $cassaCalculatedTax = 0.0;
    if ($cassaTipo !== '') {
        if ($cassaImponibile <= 0) {
            $cassaImponibile = round($taxableSum + $expensesSum, 2);
        }
        if ($cassaImponibile > 0 && $cassaAliquota > 0) {
            $cassaCalculatedAmount = round($cassaImponibile * $cassaAliquota / 100, 2);
            if ($cassaAliquotaIva > 0 && $cassaCalculatedAmount > 0) {
                $cassaCalculatedTax = round($cassaCalculatedAmount * $cassaAliquotaIva / 100, 2);
            }
            if ($cassaImporto <= 0) {
                $cassaImporto = $cassaCalculatedAmount;
            }
            if ($cassaImposta <= 0) {
                $cassaImposta = $cassaCalculatedTax;
            }
        }
    }

    // Bollo
    $bolloRequired = (!$appliesVat && ($taxableSum + $expensesSum + $nonTaxableExpensesSum + $cassaImporto) > 77.47);
    if ($bolloRequired) {
        if ($bolloToClient) {
            $bolloAmount = 2.00;
        }
    }

    $grossInvoiceTotal = round($invoiceTotal + $nonTaxableExpensesSum + $cassaImporto + $cassaImposta + $bolloAmount, 2);
    $invoiceTotal = round($grossInvoiceTotal - $ritenutaAmount, 2);
    // La scelta del tipo documento è automatica: la semplificata è ammessa solo fino a 400 euro.
    // FSM10 non prevede il blocco DatiCassaPrevidenziale: la presenza della
    // cassa richiede quindi una fattura ordinaria anche sotto i 400 euro.
    $hasDiscount = array_reduce($lineItems, static fn (bool $carry, array $item): bool => $carry || $item['discount_amount'] > 0, false);
    $isSimplified = $grossInvoiceTotal <= 400.00 && $cassaTipo === '' && $ritenutaTipo === '' && !$hasDiscount;

    // Controllo limite per fattura semplificata (<= 400 EUR)
    if ($isSimplified && $invoiceTotal > 400.00) {
        respond(422, ['success' => false, 'message' => 'Le fatture semplificate non possono superare l\'importo totale di 400,00 € (Art. 21-bis DPR 633/72)']);
    }
    $paymentAmount = round(max(0, $invoiceTotal), 2);

    // --- Riepilogo IVA (DatiRiepilogo): una riga per coppia aliquota + natura, righe e cassa incluse ---
    $vatSummary = [];
    $addToVatSummary = function (int $rate, ?string $nature, float $taxable, float $expenses, string $reference) use (&$vatSummary): void {
        $key = $rate . '|' . ($nature ?? '');
        if (!isset($vatSummary[$key])) {
            $vatSummary[$key] = ['rate' => $rate, 'nature' => $nature, 'taxable' => 0.0, 'expenses' => 0.0, 'reference' => ''];
        }
        $vatSummary[$key]['taxable'] = round($vatSummary[$key]['taxable'] + $taxable, 2);
        $vatSummary[$key]['expenses'] = round($vatSummary[$key]['expenses'] + $expenses, 2);
        if ($vatSummary[$key]['reference'] === '' && $reference !== '') {
            $vatSummary[$key]['reference'] = $reference;
        }
    };
    foreach ($lineItems as $li) {
        $addToVatSummary((int) $li['vat_rate'], $li['nature'], (float) $li['taxable_amount'], (float) $li['additional_expenses'], (string) $li['legal_reference']);
    }
    if ($nonTaxableExpensesSum > 0) {
        $addToVatSummary(0, 'N1', 0.0, $nonTaxableExpensesSum, 'Spese anticipate escluse ex art. 15 DPR 633/72');
    }
    if ($cassaTipo !== '' && $cassaImporto > 0) {
        $addToVatSummary($cassaAliquotaIva, $cassaNatura !== '' ? $cassaNatura : null, $cassaImporto, 0.0, '');
    }
    ksort($vatSummary);

    // --- Costruzione Supplier (Cedente) ---
    $supplier = (new Supplier())
        ->setCountryCode(normalizeCountry($supplierProfile['vat_country'] ?? 'IT'))
        ->setVatNumber((string) ($supplierProfile['vat_number'] ?? ''))
        ->setFiscalCode((string) ($supplierProfile['cf'] ?? ''))
        ->setTaxRegime($enumTaxRegime)
        ->setAddress(
            (new Address())
                ->setStreet((string) ($supplierProfile['address'] ?? ''))
                ->setStreetNumber((string) ($supplierProfile['street_number'] ?? ''))
                ->setZip((string) ($supplierProfile['postal_code'] ?? '00100'))
                ->setCity((string) ($supplierProfile['city'] ?? ''))
                ->setState((string) ($supplierProfile['province'] ?? ''))
                ->setCountryCode(normalizeCountry($supplierProfile['country'] ?? 'IT'))
        );

    if (!empty($supplierProfile['company_name'])) {
        $supplier->setOrganization((string) $supplierProfile['company_name']);
    } else {
        $supplier->setName((string) ($supplierProfile['first_name'] ?? ''));
        $supplier->setSurname((string) ($supplierProfile['last_name'] ?? ''));
    }

    if (!empty($supplierProfile['phone'])) {
        $supplier->setPhone((string) $supplierProfile['phone']);
    }
    if (!empty($supplierProfile['contact_email'])) {
        $supplier->setEmail((string) $supplierProfile['contact_email']);
    }
    if (!empty($supplierProfile['rea_office']) && !empty($supplierProfile['rea_number'])) {
        $supplier->setReaOffice((string) $supplierProfile['rea_office']);
        $supplier->setReaNumber((string) $supplierProfile['rea_number']);
        if (!empty($supplierProfile['share_capital'])) {
            $supplier->setCapital(number_format((float) $supplierProfile['share_capital'], 2, '.', ''));
        }
        if (!empty($supplierProfile['sole_shareholder'])) {
            $supplier->setAssociateType((string) $supplierProfile['sole_shareholder']);
        }
        if (!empty($supplierProfile['liquidation_status'])) {
            $supplier->setSettlementType((string) $supplierProfile['liquidation_status']);
        }
    }

    // --- Costruzione Customer (Cessionario) ---
    $isCompany = (($client['client_type'] ?? 'company') === 'company');
    $customer = (new Customer())
        ->setCountryCode(normalizeCountry($client['country'] ?? 'IT'))
        ->setVatNumber((string) ($client['vat_number'] ?? ''))
        ->setFiscalCode((string) ($client['cf'] ?? ''))
        ->setAddress(
            (new Address())
                ->setStreet((string) ($client['address'] ?? ''))
                ->setZip((string) ($client['postal_code'] ?? '00100'))
                ->setCity((string) ($client['city'] ?? ''))
                ->setState((string) ($client['state'] ?? ''))
                ->setCountryCode(normalizeCountry($client['country'] ?? 'IT'))
        );

    if ($isCompany) {
        $customer->setOrganization((string) ($client['company_name'] ?? ''));
    } else {
        $customer->setName((string) ($client['first_name'] ?? ''));
        $customer->setSurname((string) ($client['last_name'] ?? ''));
    }

    $customerSdi = trim((string) ($client['sdi_code'] ?? '0000000'));
    if ($customerSdi === '') {
        $customerSdi = '0000000';
    }
    $customerPec = trim((string) ($client['pec'] ?? ''));

    // --- Costruzione DigitalDocument Instance ---
    $documentInstance = (new DigitalDocumentInstance())
        ->setDocumentType($isSimplified ? DocumentType::TD07() : DocumentType::TD01())
        ->setCurrency($currency)
        ->setDocumentDate(new DateTime($issueDate))
        ->setDocumentNumber($documentNumber)
        ->setDocumentTotal($invoiceTotal)
        ->addDescription($causale !== '' ? $causale : 'Fattura n. ' . $documentNumber);

    if ($bolloRequired) {
        $documentInstance->setVirtualDuty('SI');
        $documentInstance->setVirtualDutyAmount(2.00);
    }

    if ($cassaTipo !== '' && $cassaImporto > 0) {
        $fund = (new Fund())
            ->setType($cassaTipo)
            ->setPercentage($cassaAliquota)
            ->setAmount($cassaImporto)
            ->setSubtotal($cassaImponibile > 0 ? $cassaImponibile : null)
            ->setTaxPercentage((float) $cassaAliquotaIva);
        if ($cassaNatura !== '') {
            $fund->setVatNature($cassaNatura);
        }
        if ($cassaRiferimento !== '') {
            $fund->setRepresentative($cassaRiferimento);
        }
        $documentInstance->addFund($fund);
    }

    if ($ritenutaTipo !== '') {
        $ritenutaCausali = [
            'RT01' => 'A',
            'RT02' => 'A',
            'RT03' => 'D',
            'RT04' => 'A',
            'RT05' => 'A',
        ];
        $documentInstance->addDeduction(
            (new Deduction())
                ->setType(new DeductionType($ritenutaTipo))
                ->setPercentage($ritenutaAliquota)
                ->setAmount($ritenutaAmount)
                ->setDescription($ritenutaCausali[$ritenutaTipo] ?? 'A')
        );
    }

    if ($causale !== '') {
        // Aggiungi causale extra se presente
    }

    if ($isSimplified) {
        // Righe semplificate
        foreach ($lineItems as $li) {
            $sLine = (new SimplifiedLine())
                ->setDescription($li['description'])
                ->setTotal($li['line_total'])
                ->setTaxPercentage((float) $li['vat_rate'])
                ->setTaxAmount($li['tax_amount']);
            
            if ($li['nature'] !== null) {
                $sLine->setVatNature($li['nature']);
            }
            if ($li['legal_reference'] !== '') {
                $sLine->setReference($li['legal_reference']);
            }
            $documentInstance->addSimplifiedLine($sLine);
        }
        if ($nonTaxableExpensesSum > 0) {
            $documentInstance->addSimplifiedLine(
                (new SimplifiedLine())
                    ->setDescription('Spese anticipate escluse ex art. 15 DPR 633/72')
                    ->setTotal($nonTaxableExpensesSum)
                    ->setTaxPercentage(0)
                    ->setTaxAmount(0)
                    ->setVatNature('N1')
                    ->setReference('Spese anticipate escluse ex art. 15 DPR 633/72')
            );
        }
    } else {
        // Righe ordinarie
        foreach ($lineItems as $index => $li) {
            $regLine = (new Line())
                ->setNumber($index + 1)
                ->setDescription($li['description'])
                ->setQuantity($li['quantity'])
                ->setUnitPrice($li['unit_price'])
                ->setTotal($li['taxable_amount'])
                ->setTaxPercentage($li['vat_rate']);

            if ($li['discount_amount'] > 0) {
                $discount = (new Discount())->setType(DiscountType::SC());
                if ($li['discount_type'] === 'percentage') {
                    $discount->setPercentage($li['discount_value']);
                } else {
                    $discount->setAmount($li['discount_amount']);
                }
                $regLine->addDiscount($discount);
            }
            if ($li['nature'] !== null) {
                $regLine->setVatNature($li['nature']);
            }
            $documentInstance->addLine($regLine);
        }

        // Totali riepilogo IVA (DatiRiepilogo)
        foreach ($vatSummary as $group) {
            $groupTaxable = round($group['taxable'] + $group['expenses'], 2);
            $total = (new Total())
                ->setTaxPercentage((float) $group['rate'])
                ->setTotal($groupTaxable)
                ->setTaxAmount(round($groupTaxable * $group['rate'] / 100, 2))
                ->setTaxType(VatEligibility::I());
            if ($group['expenses'] > 0) {
                $total->setOtherExpenses($group['expenses']);
            }
            if ($group['nature'] !== null) {
                $total->setVatNature($group['nature']);
            }
            $reference = $group['reference'];
            if ($reference === '' && !$appliesVat && $defaultReference !== null) {
                $reference = $defaultReference;
            }
            if ($reference !== '') {
                $total->setReference($reference);
            }
            $documentInstance->addTotal($total);
        }
    }

    // Informazioni di pagamento
    $ibanToUse = trim((string) ($supplierProfile['iban'] ?? ''));
    $bankMethods = ['MP05', 'MP16', 'MP17', 'MP19', 'MP20', 'MP21'];
    $paymentDetails = (new PaymentDetails())
        ->setMethod(new PaymentMethod($paymentMethodCode))
        ->setDueDate(new DateTime($dueDate))
        ->setAmount($paymentAmount);
    if (in_array($paymentMethodCode, $bankMethods, true) && $ibanToUse !== '') {
        $paymentDetails->setIban($ibanToUse);
    }
    if ($paymentReference !== '') {
        $paymentDetails->setPaymentCode($paymentReference);
    }

    $documentInstance->addPaymentInformations(
        (new PaymentInfo())
            ->setTerms(new PaymentTerm($paymentTermsCode))
            ->addDetails(
                $paymentDetails
            )
    );

    // --- DigitalDocument (Root) ---
    $transmissionFormat = $isSimplified ? TransmissionFormat::FSM10() : TransmissionFormat::FPR12();
    $supplierVatNum = (string) $supplierProfile['vat_number'];

    $digitalDocument = (new DigitalDocument())
        ->setCountryCode('IT')
        ->setSenderVatId($supplierVatNum)
        ->setSendingId('00001')
        ->setTransmissionFormat($transmissionFormat)
        ->setCustomerSdiCode($customerSdi)
        ->setSupplier($supplier)
        ->setCustomer($customer)
        ->addDigitalDocumentInstance($documentInstance);

    if ($customerPec !== '') {
        $digitalDocument->setCustomerPec($customerPec);
    }

    // Validazione XSD della libreria
    $validator = $digitalDocument->validate();
    if (!$validator->isValid()) {
        $errorMessages = [];
        foreach ($validator->errors() as $err) {
            $errorMessages[] = is_array($err) ? implode(', ', $err) : (string)$err;
        }
        respond(422, [
            'success' => false,
            'message' => 'Errore di validazione XML Fattura Elettronica: ' . implode(' | ', $errorMessages)
        ]);
    }

    $storageDirectory = dirname(__DIR__) . '/storage/invoices';
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('Could not create invoice storage directory');
    }

    $xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
    if (is_file($xmlPath)) {
        respond(409, ['success' => false, 'message' => 'Il file XML per questo numero documento esiste già']);
    }

    // Scrittura file XML tramite la libreria
    $digitalDocument->write($xmlPath, true);

    // Salvataggio nel database
    $pdo->beginTransaction();
    $insertInvoice = $pdo->prepare('INSERT INTO invoices (client_id, order_id, user_id, document_number, issue_date, due_date, currency, tax_regime, total, bollo, bollo_to_client, cassa_type, cassa_amount, is_paid) VALUES (:client_id, :order_id, :user_id, :document_number, :issue_date, :due_date, :currency, :tax_regime, :total, :bollo, :bollo_to_client, :cassa_type, :cassa_amount, :is_paid)');
    
    $insertInvoice->execute([
        ':client_id' => $clientId,
        ':order_id' => $orderId > 0 ? $orderId : null,
        ':user_id' => $userId,
        ':document_number' => $documentNumber,
        ':issue_date' => $issueDate,
        ':due_date' => $dueDate,
        ':currency' => $currency,
        ':tax_regime' => $taxRegimeCode,
        ':total' => number_format($invoiceTotal, 2, '.', ''),
        ':bollo' => number_format($bolloAmount, 2, '.', ''),
        ':bollo_to_client' => $bolloToClient ? 1 : 0,
        ':cassa_type' => $cassaTipo !== '' ? $cassaTipo : null,
        ':cassa_amount' => $cassaTipo !== '' ? number_format($cassaImporto, 2, '.', '') : 0.00,
        ':is_paid' => $paymentStatus === 'paid' ? 1 : 0,
    ]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->commit();

    respond(201, [
        'success' => true,
        'invoice_id' => $invoiceId,
        'document_number' => $documentNumber,
        'xml_file' => $documentNumber . '.xml',
        'is_simplified' => $isSimplified,
    ]);

} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($xmlPath) && is_file($xmlPath)) {
        @unlink($xmlPath);
    }
    error_log('Invoice save failed: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    respond(500, ['success' => false, 'message' => 'Errore durante il salvataggio: ' . $exception->getMessage()]);
}
