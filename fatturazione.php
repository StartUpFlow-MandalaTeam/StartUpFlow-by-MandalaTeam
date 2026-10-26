<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Se non è autenticato, reindirizza
if (!isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        exit;
    }
    header('Location: /auth/login.php');
    exit;
}

// Controlla se la richiesta è AJAX (per SPA)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Se NON è AJAX, reindirizza a index.php (per accesso diretto)
if (!$isAjax) {
    header('Location: /');
    exit;
}

$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$firstName = $escape($_SESSION['first_name'] ?? '');
$lastName = $escape($_SESSION['last_name'] ?? '');
$birthDate = $escape($_SESSION['birth_date'] ?? '');
$birthPlace = $escape($_SESSION['birth_place'] ?? '');
$birthCountry = $escape($_SESSION['birth_country'] ?? '');
$cf = $escape($_SESSION['cf'] ?? '');
$address = $escape($_SESSION['address'] ?? '');
$city = $escape($_SESSION['city'] ?? '');
$postalCode = $escape($_SESSION['postal_code'] ?? '');

$isProfileOk = true;

$requiredFields = [
    $firstName,
    $lastName,
    $birthDate,
    $birthPlace,
    $birthCountry,
    $cf,
    $address,
    $city,
    $postalCode
];

if (in_array('', array_map('trim', $requiredFields), true)) {
    $isProfileOk = false;
}

// Recupera dati utente (opzionale, per personalizzare)
global $pdo;
$userId = currentUserId();
$username = $_SESSION['username'] ?? 'Utente';
?>
<div class="service-page fatturazione">
    <header class="service-header">
        
        <h1>Fatturazione</h1>
        <p>Crea e visualizza le tue fatture pro-forma</p>
    </header>

    <div class="service-content">
        <nav class="invoice-actions" aria-label="Azioni fatturazione">
            <button type="button" class="invoice-action" data-invoice-action="browse">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5.5h16M4 12h16M4 18.5h16M2.5 5.5h.01M2.5 12h.01M2.5 18.5h.01" /></svg>
                <span>Fatture salvate</span>
            </button>
            <button type="button" class="invoice-action" data-invoice-action="create">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14M4 3.5h16a.5.5 0 0 1 .5.5v16a.5.5 0 0 1-.5.5H4a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5Z" /></svg>
                <span>Nuova fattura</span>
            </button>
        </nav>

        <section class="invoice-list-view" id="invoice-list-view" hidden>
            <div class="invoice-list-heading">
                <h2>Fatture salvate</h2>
                <button type="button" class="invoice-back-button" data-invoice-action="home" aria-label="Torna alle azioni fatturazione" title="Torna indietro">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h13" /></svg>
                </button>
            </div>
            <p class="invoice-list-status" id="invoice-list-status" role="status" aria-live="polite"></p>
            <div class="invoice-list" id="invoice-list"></div>
        </section>

        <div class="invoice-create-workflow" id="invoice-create-workflow" hidden>
        <div class="invoice-create-heading">
            <button type="button" class="invoice-back-button" data-invoice-action="home" aria-label="Torna alle azioni fatturazione" title="Torna alle azioni">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h13" /></svg>
            </button>
        </div>
        <?php if (!$isProfileOk): ?>
        <section class="profile-completion" id="profile-completion">
            <strong>Completa il tuo profilo per continuare</strong>
            <p>Inserisci tutti i dati richiesti per procedere con la fatturazione.</p>
            <form class="profile-modal__grid" id="profile-completion-form">
                <?php echo csrfInput(); ?>
                <label class="profile-modal__field">
                    <span>Nome</span>
                    <input type="text" name="nome" value="<?php echo $firstName; ?>" placeholder="Inserisci il nome" required>
                </label>
                <label class="profile-modal__field">
                    <span>Cognome</span>
                    <input type="text" name="cognome" value="<?php echo $lastName; ?>" placeholder="Inserisci il cognome" required>
                </label>
                <label class="profile-modal__field">
                    <span>Data di nascita</span>
                    <input type="date" name="data_di_nascita" value="<?php echo $birthDate; ?>" required>
                </label>
                <label class="profile-modal__field">
                    <span>Luogo di nascita</span>
                    <input type="text" name="luogo_di_nascita" value="<?php echo $birthPlace; ?>" placeholder="Inserisci il luogo di nascita" required>
                </label>
                <label class="profile-modal__field">
                    <span>Stato di nascita</span>
                    <select name="stato" data-fatturazione-countries-select data-selected-country="<?php echo $birthCountry; ?>" required>
                        <option value="" selected>Seleziona uno Stato</option>
                    </select>
                </label>
                <label class="profile-modal__field">
                    <span>Codice fiscale</span>
                    <input type="text" name="codice_fiscale" value="<?php echo $cf; ?>" placeholder="Inserisci il codice fiscale" maxlength="16" required>
                </label>
                <label class="profile-modal__field profile-modal__field--full">
                    <span>Indirizzo</span>
                    <input type="text" name="indirizzo" value="<?php echo $address; ?>" placeholder="Inserisci l'indirizzo" required>
                </label>
                <label class="profile-modal__field">
                    <span>Città</span>
                    <input type="text" name="citta" value="<?php echo $city; ?>" placeholder="Inserisci la città" required>
                </label>
                <label class="profile-modal__field">
                    <span>CAP</span>
                    <input type="text" name="cap" value="<?php echo $postalCode; ?>" placeholder="Inserisci il CAP" inputmode="numeric" maxlength="5" required>
                </label>
                <footer class="profile-modal__actions" style="grid-column: 1 / -1;">
                    <button type="submit" class="profile-modal__save">Avanti</button>
                </footer>
                <p id="profile-completion-status" role="status" aria-live="polite" style="grid-column: 1 / -1;"></p>
            </form>
        </section>
        <?php endif; ?>

        <section class="cliente-completion" id="cliente-completion"<?php echo $isProfileOk ? '' : ' hidden'; ?>>
            <strong>Cliente per la fattura</strong>
            <div class="client-mode-buttons" role="tablist" aria-label="Gestione cliente">
                <button type="button" class="client-mode-button is-active" data-client-mode="select">Seleziona Cliente</button>
                <button type="button" class="client-mode-button" data-client-mode="add">Aggiungi Cliente</button>
                <!-- <button type="button" class="client-mode-button" data-client-mode="edit" disabled>Modifica Cliente</button> -->
            </div>

            <div class="client-select-panel" data-client-panel="select">
                <label class="profile-modal__field">
                    <span>Cliente esistente</span>
                    <select id="client-select" required>
                        <option value="">Caricamento clienti...</option>
                    </select>
                </label>
                <button type="button" class="profile-modal__save" id="use-client-button" disabled>Usa questo cliente</button>
            </div>

            <form class="profile-modal__grid" id="cliente-form" data-client-panel="form" hidden>
                <?php echo csrfInput(); ?>
                <input type="hidden" name="id" id="client-id" value="">
                <label class="profile-modal__field">
                    <span>Tipo cliente</span>
                    <select name="client_type" id="client-type" required>
                        <option value="company" selected>Azienda</option>
                        <option value="individual">Persona fisica</option>
                    </select>
                </label>
                <label class="profile-modal__field profile-modal__field--full" data-company-field>
                    <span>Nome Compagnia</span>
                    <input type="text" name="company_name" id="company-name" placeholder="Inserisci la ragione sociale">
                </label>
                <label class="profile-modal__field" data-individual-field hidden>
                    <span>Nome</span>
                    <input type="text" name="first_name" placeholder="Inserisci il nome">
                </label>
                <label class="profile-modal__field" data-individual-field hidden>
                    <span>Cognome</span>
                    <input type="text" name="last_name" placeholder="Inserisci il cognome">
                </label>
                <label class="profile-modal__field" data-individual-field hidden>
                    <span>Codice fiscale</span>
                    <input type="text" name="cf" placeholder="Inserisci il codice fiscale" maxlength="16">
                </label>
                <label class="profile-modal__field profile-modal__field--full">
                    <span>Indirizzo</span>
                    <input type="text" name="address" placeholder="Inserisci l'indirizzo" required>
                </label>
                <label class="profile-modal__field">
                    <span>Città</span>
                    <input type="text" name="city" placeholder="Inserisci la città" required>
                </label>
                <label class="profile-modal__field">
                    <span>Provincia</span>
                    <input type="text" name="state" maxlength="10" placeholder="Es. MI" required>
                </label>
                <label class="profile-modal__field">
                    <span>CAP</span>
                    <input type="text" name="postal_code" inputmode="numeric" maxlength="10" placeholder="Inserisci il CAP" required>
                </label>
                <label class="profile-modal__field">
                    <span>Paese</span>
                    <input type="text" name="country" value="Italia" placeholder="Inserisci il paese" required>
                </label>
                <label class="profile-modal__field">
                    <span>Email</span>
                    <input type="email" name="email" placeholder="cliente@esempio.it" required>
                </label>
                <label class="profile-modal__field">
                    <span>Telefono</span>
                    <input type="tel" name="phone" placeholder="Inserisci il telefono" required>
                </label>
                <!-- <label class="profile-modal__field">
                    <span>Status CRM</span>
                    <select name="crm_status" required>
                        <option value="lead" selected>Lead</option>
                        <option value="customer">Cliente</option>
                    </select>
                </label> -->
                <footer class="profile-modal__actions" style="grid-column: 1 / -1;">
                    <button type="submit" class="profile-modal__save">Salva e continua</button>
                </footer>
            </form>
        </section>

        <div class="form-fatturazione" id="billing-form" hidden>
            <strong>Modulo Fatturazione Pro-Forma</strong>
            <form id="invoice-form" method="post" action="/invoice/process-invoice.php">
                <?php echo csrfInput(); ?>
                <div class="dati-fattura">
                    <label for="regime-fiscale">Regime Fiscale valido per la fattura che si sta generando:</label>
                    <select id="regime-fiscale" name="regime_fiscale" required>
                        <option value="regime forfettario">Regime Forfettario</option>
                        <option value="regime ordinario">Regime Ordinario</option>
                    </select>
                    <label for="data-documento">Data Documento:</label>
                    <input type="date" id="data-documento" name="issue_date" required>
                    <label for="data-scadenza">Data Scadenza:</label>
                    <input type="date" id="data-scadenza" name="due_date" required>
                    <label for="divisa">Divisa:</label>
                    <select id="divisa" name="divisa" data-fatturazione-currency-select required>
                        <option value="">Caricamento valute...</option>
                    </select>
                    <label for="num-doc">Numero Documento:</label>
                    <input id="num-doc" name="document_number" type="text" placeholder="Generazione numero..." readonly disabled required>
                </div>
                <div id="beni-servizi-form">
                    <div class="form-beni" data-beni-row>
                        <div class="form-beni-heading">
                            <strong>Beni e Servizi</strong>
                            <button type="button" class="add-beni-button" data-add-beni aria-label="Aggiungi beni e servizi">+</button>
                        </div>
                        <label for="descrizione-1">Descrizione</label>
                        <input id="descrizione-1" type="text" name="descrizione[]" required>
                        <label for="quantita-1">Quantità</label>
                        <input id="quantita-1" type="number" name="quantita[]" min="0" step="1" inputmode="numeric" required>
                        <label for="prezzo-unitario-1">Prezzo unitario</label>
                        <input id="prezzo-unitario-1" type="number" name="prezzo_unitario[]" min="0" step="0.01" inputmode="decimal" required>
                        <label for="prezzo-totale-1">Prezzo totale</label>
                        <input id="prezzo-totale-1" type="number" name="prezzo_totale[]" step="0.01" readonly required>
                        <label for="aliquota-iva-1">Aliquota IVA</label>
                        <select id="aliquota-iva-1" name="aliquota_iva[]" required>
                            <option value="0">0%</option>
                            <option value="4">4%</option>
                            <option value="5">5%</option>
                            <option value="10">10%</option>
                            <option value="22">22%</option>
                        </select>
                        <input type="hidden" name="aliquota_iva[]" value="0" data-aliquota-iva-value>
                    </div>
                </div>
                <div id="riepilogo-importi-form">
                    <h3>Riepilogo importi per aliquota</h3>
                    <div class="vat-summary-list" data-vat-summary-list></div>
                    <template data-vat-summary-template>
                        <div class="form-riepilogo" data-vat-summary-row>
                            <strong data-vat-summary-title></strong>
                            <div class="vat-summary-grid">
                                <label data-summary-label="rate">Aliquota IVA</label>
                                <input type="number" name="aliquota_iva1[]" data-summary-rate readonly>
                                <label data-summary-label="nature">Natura</label>
                                <select data-summary-nature>
                                    <option value="non_soggetta">Non soggetta</option>
                                    <option value="esente">Esente</option>
                                    <option value="fuori_campo">Fuori campo IVA</option>
                                </select>
                                <input type="hidden" name="natura[]" data-summary-nature-value>
                                <label data-summary-label="taxable">Imponibile</label>
                                <input type="number" name="imponibile[]" step="0.01" data-summary-taxable readonly>
                                <label data-summary-label="tax">Imposta</label>
                                <input type="number" name="imposta[]" step="0.01" data-summary-tax readonly>
                                <label data-summary-label="expenses">Spese accessorie</label>
                                <input type="number" name="spese_accessorie[]" step="0.01" min="0" value="0.00">
                                <label data-summary-label="reference">Riferimento normativo</label>
                                <input type="text" name="riferimento_normativo[]" placeholder="Inserisci riferimento normativo">
                            </div>
                        </div>
                    </template>
                </div>
                <div id="causale-form">
                    <h3>Causale</h3>
                    <label for="causale">Motivo o riferimento della fattura</label>
                    <textarea id="causale" name="causale" rows="4" maxlength="2000" placeholder="Aggiungi una causale o un riferimento (facoltativo)"></textarea>
                </div>
                <button type="submit" class="profile-modal__save" id="save-invoice-button">Salva fattura</button>
                <p id="invoice-save-status" role="status" aria-live="polite"></p>
            </form>

            <div class="checklist" id="billing-checklist">
                <label class="checklist-item" for="beni">
                    <input type="checkbox" id="beni" name="extras" value="beni" checked disabled>
                    <span>Beni e servizi</span>
                </label>
                <label class="checklist-item" for="riepilogo">
                    <input type="checkbox" id="riepilogo" name="extras" value="riepilogo" checked disabled>
                    <span>Riepilogo importi per aliquota</span>
                </label>
                <label class="checklist-item" for="causale-checklist">
                    <input type="checkbox" id="causale-checklist" name="extras" value="causale" checked disabled>
                    <span>Causale</span>
                </label>
            </div>
        </div>
        </div>
</div>

