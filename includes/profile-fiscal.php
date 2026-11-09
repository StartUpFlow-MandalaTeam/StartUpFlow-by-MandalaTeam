<?php

declare(strict_types=1);

/**
 * Dati fiscali del cedente/prestatore (blocco 1.2 <CedentePrestatore> di FatturaPA).
 *
 * Usato da: auth/process-profile.php (validazione e salvataggio),
 *           includes/layout/header.php (precompilazione della modale profilo),
 *           fatturazione.php (controllo "profilo completo").
 *
 * Limiti di lunghezza e obbligatorietà dalla rappresentazione tabellare FPR12.
 * Assunzione di questa versione: cedente residente in Italia (IdPaese e Nazione = IT).
 * Stabile organizzazione e rappresentante fiscale del cedente NON sono gestiti.
 */

/**
 * Regimi di riserva, usati solo se assets/data/regimi-fiscali.json non è leggibile
 * (così l'intero sito resta utilizzabile; l'errore finisce nel log).
 */
const FISCAL_REGIMES_FALLBACK = [
    ['value' => 'RF01', 'name' => 'Regime ordinario', 'supported' => true, 'applies_vat' => true, 'withholding' => true],
    ['value' => 'RF19', 'name' => 'Regime forfettario (art. 1, c. 54-89, L. 190/2014)', 'supported' => true, 'applies_vat' => false,
        'fixed_nature' => 'N2.2', 'default_reference' => "Operazione senza applicazione dell'IVA ai sensi dell'art. 1, commi 54-89, L. 190/2014",
        'withholding' => false],
];

/**
 * Catalogo dei regimi fiscali (assets/data/regimi-fiscali.json), indicizzato per codice.
 *
 * Ogni voce descrive cosa comporta il regime nella fatturazione:
 *  - supported:         selezionabile nel profilo ed utilizzabile per emettere fatture;
 *  - applies_vat:       false = IVA non applicata (aliquota sempre 0, Natura imposta dal regime);
 *  - fixed_nature:      Natura imposta quando l'IVA non si applica (obbligatoria in quel caso);
 *  - default_reference: riferimento normativo predefinito (RiferimentoNormativo, max 100 caratteri);
 *  - withholding:       il regime ammette la ritenuta d'acconto.
 * Per abilitare un nuovo regime: impostare supported e le sue regole nel JSON, e verificare il resto dell'applicazione.
 *
 * @return array<string,array<string,mixed>>
 */
function fiscalRegimeCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $path = dirname(__DIR__) . '/assets/data/regimi-fiscali.json';
    $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($data)) {
        error_log('Catalogo regimi fiscali non leggibile: ' . $path);
        $data = FISCAL_REGIMES_FALLBACK;
    }

    $catalog = [];
    foreach ($data as $entry) {
        if (!is_array($entry) || !isset($entry['value'], $entry['name'])) {
            continue;
        }
        $code = strtoupper(trim((string) $entry['value']));
        $appliesVat = (bool) ($entry['applies_vat'] ?? true);
        $fixedNature = (string) ($entry['fixed_nature'] ?? '');
        $supported = (bool) ($entry['supported'] ?? false);
        if ($supported && !$appliesVat && $fixedNature === '') {
            // Senza IVA serve una Natura: un regime configurato così produrrebbe XML scartati dallo SdI.
            error_log('Regime ' . $code . ': applies_vat=false senza fixed_nature, regime disabilitato');
            $supported = false;
        }
        $reference = (string) ($entry['default_reference'] ?? '');
        $catalog[$code] = [
            'value' => $code,
            'name' => (string) $entry['name'],
            'supported' => $supported,
            'applies_vat' => $appliesVat,
            'fixed_nature' => !$appliesVat && $fixedNature !== '' ? $fixedNature : null,
            'default_reference' => !$appliesVat && $reference !== '' ? $reference : null,
            'withholding' => (bool) ($entry['withholding'] ?? true),
        ];
    }

    return $catalog;
}

/** Regime dal codice (es. "RF19"); null se il codice non esiste nel catalogo. */
function fiscalRegime(?string $code): ?array
{
    return fiscalRegimeCatalog()[strtoupper(trim((string) $code))] ?? null;
}

/** Etichette dei soli regimi supportati, nel formato "RF19 – Regime forfettario (...)". */
function fiscalSupportedRegimes(): array
{
    $labels = [];
    foreach (fiscalRegimeCatalog() as $code => $regime) {
        if ($regime['supported']) {
            $labels[$code] = $code . ' – ' . $regime['name'];
        }
    }

    return $labels;
}

/** Etichetta leggibile di un regime salvato; riconosce anche i vecchi valori "forfettario"/"ordinario" delle fatture storiche. */
function fiscalRegimeLabel(?string $stored): string
{
    $code = strtoupper(trim((string) $stored));
    $code = ['FORFETTARIO' => 'RF19', 'ORDINARIO' => 'RF01'][$code] ?? $code;
    $regime = fiscalRegime($code);

    return $regime !== null ? $code . ' – ' . $regime['name'] : (string) $stored;
}

// Elenco dei regimi selezionabili (usato da profilo e fatturazione): derivato dal catalogo, non più scritto a mano.
define('TAX_REGIMES_SUPPORTED', fiscalSupportedRegimes());

/** Colonne di users_profiles scritte da process-profile.php (corrispondono ai campi del form). */
const FISCAL_PROFILE_COLUMNS = [
    'entity_type', 'first_name', 'last_name', 'company_name',
    'birth_date', 'birth_place', 'birth_country',
    'cf', 'vat_country', 'vat_number', 'tax_regime',
    'address', 'street_number', 'city', 'province', 'postal_code', 'country',
    'phone', 'contact_email',
    'professional_register', 'register_province', 'register_number', 'register_date',
    'rea_office', 'rea_number', 'share_capital', 'sole_shareholder', 'liquidation_status',
    'iban', 'bic',
];

/**
 * Rende un testo accettabile per l'XML: sostituisce virgolette/apostrofi/trattini tipografici,
 * elimina tab, a capo e caratteri di controllo (le normalizedString non li ammettono) e compatta gli spazi.
 */
function fiscalNormalizeText(string $value): string
{
    $value = strtr($value, [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{2032}" => "'",
        "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-',
        "\u{2026}" => '...', "\u{00A0}" => ' ',
    ]);
    $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
    $value = preg_replace('/ {2,}/', ' ', $value) ?? '';

    return trim($value);
}

/** Vero se il testo contiene solo caratteri Basic Latin / Latin-1 (come richiesto dai pattern degli schemi). */
function fiscalIsLatin1(string $value): bool
{
    return mb_check_encoding($value, 'UTF-8')
        && preg_match('/^[\x{0020}-\x{007E}\x{00A0}-\x{00FF}]*$/u', $value) === 1;
}

/** Partita IVA italiana: 11 cifre con cifra di controllo (non verifica che esista o non sia cessata). */
function fiscalIsValidVat(string $vat): bool
{
    if (preg_match('/^\d{11}$/', $vat) !== 1) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $digit = (int) $vat[$i];
        // Per le prime 10 cifre della P.IVA italiana si raddoppiano le
        // posizioni pari contando da sinistra (indici 0, 2, 4, ...).
        if ($i % 2 === 0) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }

    return (10 - $sum % 10) % 10 === (int) $vat[10];
}

/** Codice fiscale di persona fisica: 16 caratteri con carattere di controllo (ammette l'omocodia). */
function fiscalIsValidPersonalCf(string $cf): bool
{
    if (preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $cf) !== 1) {
        return false;
    }
    $odd = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];
    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $char = $cf[$i];
        if ($i % 2 === 0) {
            $sum += $odd[$char];
        } else {
            $sum += ctype_digit($char) ? (int) $char : ord($char) - 65;
        }
    }

    return chr(65 + $sum % 26) === $cf[15];
}

/** Codice fiscale ammesso per il cedente: persona fisica (16 caratteri) o, per società, 11 cifre con checksum. */
function fiscalIsValidCf(string $cf, bool $allowNumeric): bool
{
    if (strlen($cf) === 16) {
        return fiscalIsValidPersonalCf($cf);
    }

    return $allowNumeric && fiscalIsValidVat($cf);
}

/** IBAN: struttura, lunghezza (15..34; 27 per IT) e checksum mod 97. */
function fiscalIsValidIban(string $iban): bool
{
    if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
        return false;
    }
    if (strpos($iban, 'IT') === 0 && strlen($iban) !== 27) {
        return false;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $remainder = 0;
    foreach (str_split($rearranged) as $char) {
        $chunk = ctype_digit($char) ? $char : (string) (ord($char) - 55);
        $remainder = (int) ($remainder . $chunk) % 97;
    }

    return $remainder === 1;
}

/** Data Y-m-d valida e realmente esistente. */
function fiscalIsValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

/**
 * Valida e normalizza i dati del profilo (array tipo $_POST).
 *
 * @return array{errors: array<string,string>, data: array<string,mixed>}
 *         errors: campo => messaggio; data: colonne di users_profiles pronte per il salvataggio
 */
function fiscalValidateProfile(array $in): array
{
    $errors = [];
    $text = static fn (string $key): string => fiscalNormalizeText((string) ($in[$key] ?? ''));
    $compact = static fn (string $key): string => strtoupper((string) preg_replace('/\s+/', '', (string) ($in[$key] ?? '')));

    // Controlla obbligatorietà, lunghezza massima e caratteri ammessi di un campo testuale.
    $checkText = static function (string $field, string $label, string $value, int $max, bool $required) use (&$errors): void {
        if ($value === '') {
            if ($required) {
                $errors[$field] = $label . ' obbligatorio';
            }
            return;
        }
        if (mb_strlen($value) > $max) {
            $errors[$field] = $label . ' supera i ' . $max . ' caratteri consentiti';
            return;
        }
        if (!fiscalIsLatin1($value)) {
            $errors[$field] = $label . ' contiene caratteri non ammessi (usa solo lettere latine, cifre e punteggiatura semplice)';
        }
    };

    $entity = $text('tipo_soggetto') !== '' ? $text('tipo_soggetto') : 'individual';
    if (!in_array($entity, ['individual', 'company'], true)) {
        $errors['entity_type'] = 'Tipo soggetto non valido';
        $entity = 'individual';
    }
    $isCompany = $entity === 'company';

    $firstName = $text('nome');
    $lastName = $text('cognome');
    $companyName = $isCompany ? $text('denominazione') : '';
    $checkText('first_name', 'Nome', $firstName, 60, true);
    $checkText('last_name', 'Cognome', $lastName, 60, true);
    if ($isCompany) {
        $checkText('company_name', 'Denominazione', $companyName, 80, true);
    }

    // Dati di nascita (non fanno parte di FatturaPA, ma restano richiesti dal profilo).
    $birthDate = $text('data_di_nascita');
    $birthPlace = $text('luogo_di_nascita');
    $birthCountry = $text('stato');
    if ($birthDate === '' || !fiscalIsValidDate($birthDate)) {
        $errors['birth_date'] = $birthDate === '' ? 'Data di nascita obbligatoria' : 'Data di nascita non valida';
    }
    $checkText('birth_place', 'Luogo di nascita', $birthPlace, 100, true);
    $checkText('birth_country', 'Stato di nascita', $birthCountry, 100, true);

    // Identificativi fiscali.
    $cf = $compact('codice_fiscale');
    if ($cf === '') {
        $errors['cf'] = 'Codice fiscale obbligatorio';
    } elseif (!fiscalIsValidCf($cf, $isCompany)) {
        $errors['cf'] = $isCompany
            ? 'Codice fiscale non valido (16 caratteri, oppure 11 cifre per le società)'
            : 'Codice fiscale non valido (16 caratteri con carattere di controllo corretto)';
    }

    $vat = $compact('partita_iva');
    if ($vat === '') {
        $errors['vat_number'] = 'Partita IVA obbligatoria per emettere fatture';
    } elseif (!fiscalIsValidVat($vat)) {
        $errors['vat_number'] = 'Partita IVA non valida (11 cifre con cifra di controllo corretta)';
    }

    $regime = strtoupper($text('regime_fiscale'));
    if (!isset(TAX_REGIMES_SUPPORTED[$regime])) {
        $errors['tax_regime'] = 'Seleziona un regime fiscale valido';
    }

    // Sede / domicilio fiscale (residenti in Italia).
    $address = $text('indirizzo');
    $streetNumber = $text('civico');
    $city = $text('citta');
    $province = $compact('provincia');
    $postalCode = $text('cap');
    $checkText('address', 'Indirizzo', $address, 60, true);
    $checkText('street_number', 'Numero civico', $streetNumber, 8, false);
    $checkText('city', 'Comune', $city, 60, true);
    if (preg_match('/^[A-Z]{2}$/', $province) !== 1) {
        $errors['province'] = 'Provincia obbligatoria (sigla di 2 lettere, es. MI)';
    }
    if (preg_match('/^\d{5}$/', $postalCode) !== 1) {
        $errors['postal_code'] = 'CAP non valido (5 cifre)';
    }

    // Contatti (facoltativi). Il telefono per lo SdI è lungo da 5 a 12 caratteri.
    $phone = (string) preg_replace('/[\s().-]+/', '', (string) ($in['telefono'] ?? ''));
    if ($phone !== '') {
        if (preg_match('/^\+?\d+$/', $phone) !== 1) {
            $errors['phone'] = 'Telefono non valido (solo cifre ed eventuale + iniziale)';
        } elseif (strlen($phone) < 5 || strlen($phone) > 12) {
            $errors['phone'] = 'Telefono: lo SdI ammette da 5 a 12 caratteri (ometti il prefisso +39 se serve)';
        }
    }
    $contactEmail = $text('email_contatto');
    if ($contactEmail !== '') {
        if (mb_strlen($contactEmail) < 7 || mb_strlen($contactEmail) > 256 || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false || !fiscalIsLatin1($contactEmail)) {
            $errors['contact_email'] = 'Email di contatto non valida (da 7 a 256 caratteri)';
        }
    }

    // Albo professionale (tutti i campi sono facoltativi singolarmente).
    $register = $text('albo');
    $registerProvince = $compact('provincia_albo');
    $registerNumber = $text('numero_albo');
    $registerDate = $text('data_albo');
    $checkText('professional_register', 'Albo professionale', $register, 60, false);
    $checkText('register_number', 'Numero iscrizione albo', $registerNumber, 60, false);
    if ($registerProvince !== '' && preg_match('/^[A-Z]{2}$/', $registerProvince) !== 1) {
        $errors['register_province'] = 'Provincia albo non valida (2 lettere)';
    }
    if ($registerDate !== '' && !fiscalIsValidDate($registerDate)) {
        $errors['register_date'] = 'Data iscrizione albo non valida';
    }

    // Iscrizione REA: se compili uno qualsiasi dei campi, ufficio, numero e stato di liquidazione diventano obbligatori.
    $reaOffice = $compact('ufficio_rea');
    $reaNumber = $text('numero_rea');
    $capitalRaw = str_replace(',', '.', trim((string) ($in['capitale_sociale'] ?? '')));
    $soleShareholder = strtoupper($text('socio_unico'));
    $liquidation = strtoupper($text('stato_liquidazione'));
    $hasRea = $reaOffice !== '' || $reaNumber !== '' || $capitalRaw !== '' || $soleShareholder !== '' || $liquidation !== '';
    $shareCapital = null;
    if ($hasRea) {
        if (preg_match('/^[A-Z]{2}$/', $reaOffice) !== 1) {
            $errors['rea_office'] = 'Ufficio REA obbligatorio (sigla provincia di 2 lettere)';
        }
        $checkText('rea_number', 'Numero REA', $reaNumber, 20, true);
        if (!in_array($liquidation, ['LS', 'LN'], true)) {
            $errors['liquidation_status'] = 'Stato di liquidazione obbligatorio con iscrizione REA';
        }
        if ($soleShareholder !== '' && !in_array($soleShareholder, ['SU', 'SM'], true)) {
            $errors['sole_shareholder'] = 'Valore socio unico non valido';
        }
        if ($capitalRaw !== '') {
            if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $capitalRaw) !== 1) {
                $errors['share_capital'] = 'Capitale sociale non valido (max 12 cifre intere e 2 decimali)';
            } else {
                $shareCapital = number_format((float) $capitalRaw, 2, '.', '');
            }
        }
    }

    // Pagamento (facoltativo).
    $iban = $compact('iban');
    $bic = $compact('bic');
    if ($iban !== '' && !fiscalIsValidIban($iban)) {
        $errors['iban'] = 'IBAN non valido';
    }
    if ($bic !== '' && preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) !== 1) {
        $errors['bic'] = 'BIC non valido (8 o 11 caratteri)';
    }

    $nullable = static fn (string $value): ?string => $value === '' ? null : $value;

    return [
        'errors' => $errors,
        'data' => [
            'entity_type' => $entity,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $nullable($companyName),
            'birth_date' => $birthDate,
            'birth_place' => $birthPlace,
            'birth_country' => $birthCountry,
            'cf' => $cf,
            'vat_country' => 'IT',
            'vat_number' => $vat,
            'tax_regime' => $regime,
            'address' => $address,
            'street_number' => $nullable($streetNumber),
            'city' => $city,
            'province' => $province,
            'postal_code' => $postalCode,
            'country' => 'IT',
            'phone' => $nullable($phone),
            'contact_email' => $nullable($contactEmail),
            'professional_register' => $nullable($register),
            'register_province' => $nullable($registerProvince),
            'register_number' => $nullable($registerNumber),
            'register_date' => $nullable($registerDate),
            'rea_office' => $hasRea ? $nullable($reaOffice) : null,
            'rea_number' => $hasRea ? $nullable($reaNumber) : null,
            'share_capital' => $shareCapital,
            'sole_shareholder' => $hasRea ? $nullable($soleShareholder) : null,
            'liquidation_status' => $hasRea ? $nullable($liquidation) : null,
            'iban' => $nullable($iban),
            'bic' => $nullable($bic),
        ],
    ];
}

/** Legge la riga di users_profiles dell'utente (array vuoto se non esiste). */
function loadUserFiscalProfile(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT * FROM users_profiles WHERE user_id = :user_id LIMIT 1');
    $statement->execute([':user_id' => $userId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

/**
 * Elenca i dati fiscali mancanti o non validi per poter emettere fatture.
 *
 * @return array<string,string> colonna => etichetta leggibile (vuoto = profilo completo)
 */
function fiscalProfileMissing(array $row): array
{
    $isCompany = ($row['entity_type'] ?? 'individual') === 'company';
    $required = [
        'first_name' => 'nome',
        'last_name' => 'cognome',
        'cf' => 'codice fiscale',
        'vat_number' => 'partita IVA',
        'tax_regime' => 'regime fiscale',
        'address' => 'indirizzo',
        'city' => 'comune',
        'province' => 'provincia',
        'postal_code' => 'CAP',
    ];
    if ($isCompany) {
        $required = ['company_name' => 'denominazione'] + $required;
    }

    $missing = [];
    foreach ($required as $column => $label) {
        if (trim((string) ($row[$column] ?? '')) === '') {
            $missing[$column] = $label;
        }
    }

    // Dati presenti ma non conformi (tipico dei profili salvati prima di questa versione).
    $cf = strtoupper((string) ($row['cf'] ?? ''));
    if (!isset($missing['cf']) && !fiscalIsValidCf($cf, $isCompany)) {
        $missing['cf'] = 'codice fiscale (non valido)';
    }
    if (!isset($missing['vat_number']) && !fiscalIsValidVat((string) $row['vat_number'])) {
        $missing['vat_number'] = 'partita IVA (non valida)';
    }
    if (!isset($missing['tax_regime']) && !isset(TAX_REGIMES_SUPPORTED[(string) $row['tax_regime']])) {
        $missing['tax_regime'] = 'regime fiscale (non supportato)';
    }
    if (!isset($missing['postal_code']) && preg_match('/^\d{5}$/', (string) $row['postal_code']) !== 1) {
        $missing['postal_code'] = 'CAP (5 cifre)';
    }
    foreach (['address' => 'indirizzo', 'city' => 'comune', 'first_name' => 'nome', 'last_name' => 'cognome'] as $column => $label) {
        if (!isset($missing[$column]) && mb_strlen((string) $row[$column]) > 60) {
            $missing[$column] = $label . ' (oltre 60 caratteri)';
        }
    }

    return $missing;
}

/** Valori della riga già escapati per l'output HTML (tutte le colonne, stringa vuota se assenti). */
function fiscalFormValues(array $row): array
{
    $values = [];
    foreach (FISCAL_PROFILE_COLUMNS as $column) {
        $values[$column] = htmlspecialchars((string) ($row[$column] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    if ($values['entity_type'] === '') {
        $values['entity_type'] = 'individual';
    }

    return $values;
}