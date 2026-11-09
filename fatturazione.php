<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile-fiscal.php';

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

// Il profilo è "completo" quando ci sono i dati fiscali del cedente richiesti da FatturaPA
// (partita IVA, regime, sede...), letti dal DB: la sessione può essere vecchia.
global $pdo;
$userId = currentUserId();
$fiscalRow = $userId !== null ? loadUserFiscalProfile($pdo, $userId) : [];
$missingFiscal = fiscalProfileMissing($fiscalRow);
$isProfileOk = $missingFiscal === [];
$username = $_SESSION['username'] ?? 'Utente';
?>
<div class="service-page fatturazione">
    <header class="service-header">
        <h1>Fatturazione</h1>
        <p>Crea e visualizza le tue fatture</p>
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
            <h2> Crea fattura</h2>
            <button type="button" class="invoice-back-button" data-invoice-action="home" aria-label="Torna alle azioni fatturazione" title="Torna alle azioni">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 5-7 7 7 7M8 12h13" /></svg>
            </button>
        </div>

        <?php if (!$isProfileOk): ?>
        <section class="profile-completion" id="profile-completion">
            <strong>Completa i dati fiscali per continuare</strong>
            <p>Per emettere fatture elettroniche servono i dati del cedente (partita IVA, regime fiscale, sede, codice fiscale valido).
               Mancano o non sono validi: <?php echo $escape(implode(', ', array_values($missingFiscal))); ?>.</p>
            <button type="button" class="profile-modal__save" data-open-profile-modal>Apri il profilo</button>
        </section>
        <?php endif; ?>

        <section class="cliente-completion" id="cliente-completion"<?php echo $isProfileOk ? '' : ' hidden'; ?>>
            <strong>1) Definisci il cliente intestatario della fattura</strong>
            <div class="client-mode-buttons" role="tablist" aria-label="Gestione cliente">
                <button type="button" class="client-mode-button is-active" data-client-mode="select">Seleziona Cliente</button>
                <button type="button" class="client-mode-button" data-client-mode="add">Nuovo Cliente</button>
                <!-- <button type="button" class="client-mode-button" data-client-mode="edit" disabled>Modifica Cliente</button> -->
            </div>

            <div class="client-select-panel" data-client-panel="select">
                <label class="profile-modal__field">
                    <span>Cliente esistente</span>
                    <select id="client-select" required>
                        <option value="">Caricamento clienti...</option>
                    </select>
                </label>
                <label class="profile-modal__field" id="order-select-container">
                    <span>Protocollo ordine</span>
                    <select id="order-select">
                        <option value="">Seleziona un ordine</option>
                    </select>
                </label>
                <div style="margin-top: 1rem;">
                    <button type="button" class="profile-modal__save" id="use-client-button" disabled>Vai!</button>
                </div>
            </div>

            <form class="profile-modal__grid" id="cliente-form" data-client-panel="form" hidden>
                <?php echo csrfInput(); ?>
                <input type="hidden" name="id" id="client-id" value="">
                <label class="profile-modal__field">
                    <span>Tipo cliente</span>
                    <select name="client_type" id="client-type" required>
                        <option value="company" selected>Società</option>
                        <option value="individual">Persona fisica</option>
                    </select>
                </label>
                <label class="profile-modal__field profile-modal__field--full" data-company-field>
                    <span>Denominazione</span>
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
                <label class="profile-modal__field" data-company-field>
                    <span>P.IVA</span>
                    <input type="text" name="vat_number" placeholder="Inserisci la Partita IVA (11 cifre)" maxlength="11" inputmode="numeric" pattern="[0-9]{11}">
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
            <strong>2) Modulo Fatturazione</strong>
            <form id="invoice-form" method="post" action="/invoice/process-invoice.php">
                <?php echo csrfInput(); ?>
                <?php
                $taxRegimeCode = strtoupper((string) ($fiscalRow['tax_regime'] ?? ''));
                $regimeInfo = fiscalRegime($taxRegimeCode);
                $taxRegimeLabel = isset(TAX_REGIMES_SUPPORTED[$taxRegimeCode]) ? TAX_REGIMES_SUPPORTED[$taxRegimeCode] : null;
                $hasRegime = $taxRegimeCode !== '' && $taxRegimeLabel !== null;
                ?>
                <div class="dati-fattura">
                    <input type="hidden" id="invoice-type" name="invoice_type" value="semplificata">
                    <div class="dati-fattura__field">
                        <label for="invoice-type-status">Tipo documento</label>
                        <div id="invoice-type-status" role="status" aria-live="polite">
                            <strong>Fattura semplificata</strong> (default; ordinaria oltre € 400,00)
                        </div>
                    </div>
                    <div class="dati-fattura__field">
                        <label for="regime-fiscale-display">Regime fiscale</label>
                        <?php if ($hasRegime): ?>
                            <span id="regime-fiscale-display"><?php echo $escape($taxRegimeLabel); ?></span>
                            <input type="hidden" id="regime-fiscale" name="tax_regime" value="<?php echo $escape($taxRegimeCode); ?>"
                                   data-applies-vat="<?php echo $regimeInfo['applies_vat'] ? '1' : '0'; ?>"
                                   data-fixed-nature="<?php echo $escape($regimeInfo['fixed_nature']); ?>"
                                   data-default-reference="<?php echo $escape($regimeInfo['default_reference']); ?>"
                                   data-withholding="<?php echo $regimeInfo['withholding'] ? '1' : '0'; ?>">
                        <?php else: ?>
                            <span id="regime-fiscale-display" style="color: #dc2626; font-weight: bold;">Regime fiscale non specificato in anagrafica</span>
                            <input type="hidden" id="regime-fiscale" name="tax_regime" value="">
                            <div style="margin: 0.5rem 0; padding: 0.75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; color: #991b1b; font-size: 0.9rem;">
                                <strong>Attenzione:</strong> non hai specificato il regime fiscale in anagrafica. Vai nel profilo utente (anagrafica) per selezionare un regime fiscale valido prima di emettere fatture.
                            </div>
                        <?php endif; ?>
                    </div>
                    <label for="data-documento">Data Documento:</label>
                    <input type="date" id="data-documento" name="issue_date" required>
                    <label for="data-scadenza">Data Scadenza:</label>
                    <input type="date" id="data-scadenza" name="due_date" required>
                    <label for="divisa">Divisa:</label>
                    <select id="divisa" name="divisa" data-fatturazione-currency-select required>
                        <option value="">Caricamento valute...</option>
                    </select>
                    <label for="num-doc">Numero Documento:</label>
                    <input id="num-doc" name="document_number" type="text" placeholder="Generazione numero..." readonly required>
                </div>
                <div id="pagamento-form">
                    <strong>Pagamento</strong>
                    <input type="hidden" id="order-id" name="order_id" value="">
                    <div class="vat-summary-grid">
                        <label for="stato-pagamento">Stato pagamento</label>
                        <select id="stato-pagamento" name="payment_status" required>
                            <option value="unpaid">Da pagare</option>
                            <option value="partial">Pagamento parziale</option>
                            <option value="paid">Pagato</option>
                        </select>
                        <label for="condizioni-pagamento">Condizioni di pagamento</label>
                        <select id="condizioni-pagamento" name="payment_terms" required>
                            <?php foreach ([
                                ['TP01', 'Pagamento a rate'],
                                ['TP02', 'Pagamento completo'],
                                ['TP03', 'Pagamento anticipato'],
                            ] as $term): ?>
                                <option value="<?php echo $escape($term[0]); ?>"><?php echo $escape($term[1]); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="modalita-pagamento">Modalità di pagamento</label>
                        <select id="modalita-pagamento" name="payment_method" required>
                            <option value="">Caricamento modalità...</option>
                        </select>
                        <label for="importo-pagamento">Importo da pagare</label>
                        <input type="number" id="importo-pagamento" name="payment_amount" min="0" step="0.01" required>
                        <label for="riferimento-pagamento">Riferimento pagamento</label>
                        <input type="text" id="riferimento-pagamento" name="payment_reference" maxlength="100" placeholder="CRO, identificativo transazione (facoltativo)">
                        <div id="pagamento-effettuato" hidden>
                            <span>Pagamento registrato</span>
                            <strong id="pagamento-effettuato-dettaglio"></strong>
                        </div>
                    </div>
                </div>
                <div class="dati-fattura__bollo" id="bollo-container" hidden>
                    <input type="checkbox" id="bollo-to-client" name="bollo_to_client" value="1">
                    <label for="bollo-to-client">Addebito marca da bollo (€ 2,00) al cliente (imponibile > € 77,47)</label>
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
                        <input id="quantita-1" type="number" name="quantita[]" min="0" step="0.01" inputmode="decimal" required>
                        <label for="prezzo-unitario-1">Prezzo unitario</label>
                        <input id="prezzo-unitario-1" type="number" name="prezzo_unitario[]" min="0" step="0.01" inputmode="decimal" required>
                        <label for="tipo-sconto-1">Sconto</label>
                        <div class="form-beni-discount">
                            <select id="tipo-sconto-1" name="tipo_sconto[]" aria-label="Tipo sconto">
                                <option value="none">Nessuno</option>
                                <option value="percentage">%</option>
                                <option value="amount">€</option>
                            </select>
                            <input id="valore-sconto-1" type="number" name="valore_sconto[]" min="0" step="0.01" value="0" aria-label="Valore sconto">
                        </div>
                        <label for="aliquota-iva-1">Aliquota IVA</label>
                        <select id="aliquota-iva-1" name="aliquota_iva[]" required>
                            <option value="0">0%</option>
                            <option value="4">4%</option>
                            <option value="5">5%</option>
                            <option value="10">10%</option>
                            <option value="22">22%</option>
                        </select>
                        <input type="hidden" name="aliquota_iva[]" value="0" data-aliquota-iva-value>
                        <label for="prezzo-totale-1">Prezzo totale</label>
                        <input id="prezzo-totale-1" type="number" name="prezzo_totale[]" step="0.01" readonly required>
                    </div>
                </div>
                <div id="riepilogo-importi-form">
                    <h3>Riepilogo importi per aliquota</h3>
                    <div class="vat-summary-list" data-vat-summary-list></div>
                    <div class="vat-summary-total" data-vat-summary-total>
                        <span>Imposta IVA totale</span>
                        <strong>0,00</strong>
                    </div>
                    <template data-vat-summary-template>
                        <div class="form-riepilogo" data-vat-summary-row>
                            <strong data-vat-summary-title></strong>
                            <div class="vat-summary-grid">
                                <label data-summary-label="rate">Aliquota IVA</label>
                                <input type="number" name="aliquota_iva1[]" data-summary-rate readonly>
                                <label data-summary-label="tax">Imposta IVA</label>
                                <input type="number" name="imposta[]" step="0.01" data-summary-tax readonly>
                                <label data-summary-label="nature">Natura</label>
                                <select data-summary-nature>
                                    <option value="">Caricamento Natura IVA...</option>
                                </select>
                                <input type="hidden" name="natura[]" data-summary-nature-value>
                                <label data-summary-label="taxable">Imponibile</label>
                                <input type="number" name="imponibile[]" step="0.01" data-summary-taxable readonly>
                                <label data-summary-label="expenses">Spese accessorie imponibili</label>
                                <input type="number" name="spese_accessorie[]" step="0.01" min="0" value="0.00">
                                <label data-summary-label="non-taxable-expenses">Spese escluse ex art. 15</label>
                                <input type="number" name="spese_accessorie_non_imponibili[]" step="0.01" min="0" value="0.00">
                                <label data-summary-label="reference">Riferimento normativo</label>
                                <input type="text" name="riferimento_normativo[]" maxlength="100" placeholder="Inserisci riferimento normativo">
                            </div>
                        </div>
                    </template>
                </div>
                <div id="cassa-form">
                    <h3>Cassa previdenziale</h3>
                    <div class="form-riepilogo">
                        <div class="vat-summary-grid cassa-grid">
                            <label for="cassa-tipo">Cassa</label>
                            <select id="cassa-tipo" name="cassa_tipo" class="cassa-grid__wide">
                                <option value="">Caricamento casse previdenziali...</option>
                            </select>
                            <label for="cassa-aliquota" data-cassa-manual-label hidden>Aliquota cassa (%)</label>
                            <input type="number" id="cassa-aliquota" name="cassa_aliquota" min="0.01" max="100" step="0.01" inputmode="decimal" placeholder="Es. 4.00" data-cassa-manual-input hidden>
                        </div>
                        <div class="vat-summary-grid cassa-grid" data-cassa-details hidden>
                            <label for="cassa-imponibile">Imponibile cassa</label>
                            <input type="number" id="cassa-imponibile" name="cassa_imponibile" min="0.01" step="0.01" inputmode="decimal">
                            <label for="cassa-aliquota-iva">Aliquota IVA contributo</label>
                            <select id="cassa-aliquota-iva" name="cassa_aliquota_iva">
                                <option value="0">0%</option>
                                <option value="4">4%</option>
                                <option value="5">5%</option>
                                <option value="10">10%</option>
                                <option value="22" selected>22%</option>
                            </select>
                            <label for="cassa-natura">Natura</label>
                            <select id="cassa-natura" name="cassa_natura">
                                <option value="">Caricamento Natura IVA...</option>
                            </select>
                            <label for="cassa-importo">Contributo cassa</label>
                            <input type="number" id="cassa-importo" step="0.01" readonly tabindex="-1">
                            <label for="cassa-imposta">IVA sul contributo</label>
                            <input type="number" id="cassa-imposta" step="0.01" readonly tabindex="-1">
                            <label for="cassa-riferimento">Riferimento amministrazione</label>
                            <input type="text" id="cassa-riferimento" name="cassa_riferimento" maxlength="20" placeholder="Facoltativo">
                        </div>
                    </div>
                </div>
                <div id="ritenuta-form" hidden>
                    <h3>Ritenuta d'acconto</h3>
                    <div class="form-riepilogo">
                        <div class="vat-summary-grid cassa-grid">
                            <label for="ritenuta-tipo">Tipo ritenuta</label>
                            <select id="ritenuta-tipo" name="ritenuta_tipo" class="cassa-grid__wide">
                                <option value="">Nessuna ritenuta</option>
                            </select>
                        </div>
                        <div class="vat-summary-grid cassa-grid">
                            <label for="ritenuta-aliquota">Aliquota ritenuta (%)</label>
                            <input type="number" id="ritenuta-aliquota" name="ritenuta_aliquota" min="0" max="100" step="0.01" value="0">
                            <label for="ritenuta-importo">Importo ritenuta</label>
                            <input type="number" id="ritenuta-importo" step="0.01" readonly tabindex="-1" value="0.00">
                        </div>
                    </div>
                </div>
                <div id="causale-form">
                    <h3>Causale</h3>
                    <label for="causale">Motivo o riferimento della fattura</label>
                    <textarea id="causale" name="causale" rows="4" maxlength="2000" placeholder="Aggiungi una causale o un riferimento (facoltativo)"></textarea>
                </div>
                <button type="submit" class="profile-modal__save" id="save-invoice-button">Salva fattura</button>
                <p id="invoice-save-status" role="status" aria-live="polite"></p>
            </form>
        </div>
    </div>
</div>
