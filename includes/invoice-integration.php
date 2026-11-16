<?php

declare(strict_types=1);

/**
 * Regole condivise per i documenti di integrazione/autofattura TD17, TD18, TD19.
 * Usato da fatturazione.php (form), generate-invoice-number.php (numerazione)
 * e process-invoice.php (validazione e generazione XML).
 *
 * Riferimenti:
 *  - Specifiche tecniche FatturaPA v1.9, par. 3.2.1 – 3.2.3 e Appendice 1 (codici errore 00400…00476)
 *  - Guida alla compilazione delle fatture elettroniche e dell'esterometro (Agenzia delle Entrate)
 *  - Art. 17 c.2 DPR 633/72; artt. 46-47 DL 331/93 (acquisti intracomunitari)
 */

/** Sezionale dedicato alle integrazioni/autofatture (numerazione distinta da quella delle fatture di vendita). */
const INTEGRATION_SERIES = 'INT';

/** Sezionale delle fatture di vendita: ADATTALO a quello che usi oggi (deve contenere solo lettere maiuscole). */
const ORDINARY_SERIES = 'FT';

/** Peso massimo della copia della fattura estera (il file XML finale non può superare 5 MB, base64 incluso). */
const INTEGRATION_ATTACHMENT_MAX_BYTES = 3 * 1024 * 1024;

/**
 * Natura IVA ammessa quando l'aliquota è 0%, per tipo documento.
 * Si basa sulla guida AdE (TD17: N2.1, N2.2, N3.4, N4; TD18: N3.5, N3.6, N4; TD19: N2.2, N3.5, N3.6, N4)
 * più N3.5 (plafond) anche per i servizi. Le nature N1 (escluse art. 15), N3.1-N3.3 (cessioni), N5, N6.x (inversione
 * contabile: è l'effetto stesso del TD17/18/19) e N7 non sono previste per questi documenti.
 */
const INTEGRATION_DOC_TYPES = [
    'TD17' => [
        'label' => 'Integrazione/autofattura per acquisto di servizi dall\'estero',
        'natures' => ['N2.1', 'N2.2', 'N3.4', 'N3.5', 'N4'],
        'eu_only' => false,
        'hint' => 'Servizi acquistati da un fornitore non residente (UE o extra-UE). Data documento: data di ricezione della fattura (servizi UE) oppure data di effettuazione dell\'operazione (servizi extra-UE).',
    ],
    'TD18' => [
        'label' => 'Integrazione per acquisto di beni intracomunitari',
        'natures' => ['N3.5', 'N3.6', 'N4'],
        'eu_only' => true,
        'hint' => 'Beni acquistati da un fornitore di un altro Stato UE. Data documento: data di ricezione della fattura (o altra data compresa nel mese di ricezione).',
    ],
    'TD19' => [
        'label' => 'Integrazione/autofattura per acquisto di beni ex art. 17 c.2 DPR 633/72',
        'natures' => ['N2.2', 'N3.5', 'N3.6', 'N4'],
        'eu_only' => false,
        'hint' => 'Beni già presenti in Italia acquistati da un fornitore non residente. Data documento: data di ricezione della fattura (fornitore UE) oppure data di effettuazione dell\'operazione (fornitore extra-UE).',
    ],
];

const INTEGRATION_NATURE_LABELS = [
    'N2.1' => 'Non soggetta ad IVA – artt. da 7 a 7-septies DPR 633/72',
    'N2.2' => 'Non soggetta ad IVA – altri casi',
    'N3.4' => 'Non imponibile – operazioni assimilate alle cessioni all\'esportazione',
    'N3.5' => 'Non imponibile – a seguito di dichiarazioni d\'intento',
    'N3.6' => 'Non imponibile – altre operazioni che non concorrono alla formazione del plafond',
    'N4' => 'Esente',
];

/** RiferimentoNormativo predefinito (obbligatorio quando è presente la Natura). Modificabile dall'utente. */
const INTEGRATION_NATURE_REFERENCES = [
    'N2.1' => 'Operazione non soggetta ad IVA ai sensi degli artt. da 7 a 7-septies DPR 633/72',
    'N2.2' => 'Operazione non soggetta ad IVA – altri casi',
    'N3.4' => 'Operazione non imponibile assimilata alle cessioni all\'esportazione',
    'N3.5' => 'Operazione non imponibile a seguito di dichiarazione d\'intento (art. 8 c.1 lett. c DPR 633/72)',
    'N3.6' => 'Operazione non imponibile che non concorre alla formazione del plafond',
    'N4' => 'Operazione esente da IVA (art. 10 DPR 633/72)',
];

/** Stati membri UE (ISO 3166-1 alpha-2, Italia esclusa) + XI (Irlanda del Nord, solo beni). */
const INTEGRATION_EU_COUNTRIES = [
    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
    'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'XI',
];

function integrationIsType(string $code): bool
{
    return isset(INTEGRATION_DOC_TYPES[$code]);
}

/** Configurazione passata al JS del form (data-config). */
function integrationClientConfig(): array
{
    $types = [];
    foreach (INTEGRATION_DOC_TYPES as $code => $def) {
        $types[$code] = ['label' => $def['label'], 'natures' => $def['natures'], 'hint' => $def['hint']];
    }
    return [
        'types' => $types,
        'natureLabels' => INTEGRATION_NATURE_LABELS,
        'references' => INTEGRATION_NATURE_REFERENCES,
        'maxBytes' => INTEGRATION_ATTACHMENT_MAX_BYTES,
    ];
}

/** Serie del numero documento per il tipo richiesto (AUTO/TD01/TD07 = fattura di vendita). */
function invoiceSeriesFor(string $documentType): string
{
    return integrationIsType($documentType) ? INTEGRATION_SERIES : ORDINARY_SERIES;
}

/** Scadenza di trasmissione allo SdI: 15 del mese successivo a quello della data documento. */
function integrationDeadline(string $issueDate): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
    if ($date === false) {
        return null;
    }
    $next = $date->modify('first day of next month');

    return $next->setDate((int) $next->format('Y'), (int) $next->format('n'), 15);
}

/**
 * Risolve il codice ISO del paese del fornitore. A differenza di normalizeCountry() NON ripiega su "IT":
 * per TD17/18/19 un IdPaese italiano farebbe scartare il file (errore 00473).
 */
function integrationResolveCountry(?string $country): ?string
{
    $value = strtoupper(trim((string) $country));
    if ($value === '') {
        return null;
    }
    if (strlen($value) === 2) {
        return $value === 'EL' ? 'GR' : $value;
    }
    static $map = null;
    if ($map === null) {
        $map = [];
        $path = dirname(__DIR__) . '/assets/data/countries.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        foreach (is_array($data) ? $data : [] as $entry) {
            if (isset($entry['code'], $entry['name'])) {
                $map[strtoupper((string) $entry['name'])] = strtoupper((string) $entry['code']);
            }
        }
        $map['ITALIA'] = 'IT';
        $map['ITALY'] = 'IT';
    }
    return $map[$value] ?? null;
}

/**
 * Identificativo fiscale del fornitore estero: rimuove separatori e prefisso paese (IdCodice non lo contiene).
 * Restituisce '' se assente.
 */
function integrationCleanVatId(?string $vat, string $countryCode): string
{
    $clean = strtoupper(preg_replace('/[\s.\-\/]/', '', (string) $vat) ?? '');
    foreach (array_unique([$countryCode, $countryCode === 'GR' ? 'EL' : $countryCode]) as $prefix) {
        if ($prefix !== '' && str_starts_with($clean, $prefix) && strlen($clean) > strlen($prefix)) {
            $clean = substr($clean, strlen($prefix));
            break;
        }
    }
    return $clean;
}
