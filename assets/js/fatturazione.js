//// Registra un listener su document sostituendo quello della precedente inizializzazione.
// Serve perché la pagina è caricata via SPA: senza questo, ogni navigazione accumulerebbe
// listener "orfani" che lavorano su elementi DOM ormai rimossi.
const bindDocument = (key, eventName, handler) => {
    window.__fatturazioneListeners = window.__fatturazioneListeners || {};
    const previous = window.__fatturazioneListeners[key];
    if (previous) document.removeEventListener(previous.eventName, previous.handler);
    window.__fatturazioneListeners[key] = { eventName, handler };
    document.addEventListener(eventName, handler);
};

let paymentMethodsPromise;

const initializeFatturazione = () => {
    const countrySelect = document.querySelector('[data-fatturazione-countries-select]');
    const currencySelect = document.querySelector('[data-fatturazione-currency-select]');
    const profileForm = document.getElementById('profile-completion-form');
    const clientCompletion = document.getElementById('cliente-completion');
    const clientForm = document.getElementById('cliente-form');
    const clientSelect = document.getElementById('client-select');
    const clientModeButtons = document.querySelectorAll('[data-client-mode]');
    const clientSelectPanel = document.querySelector('[data-client-panel="select"]');
    const useClientButton = document.getElementById('use-client-button');
    const billingForm = document.getElementById('billing-form');
    const regimeSelect = document.getElementById('regime-fiscale');
    // Le regole del regime (catalogo regimi-fiscali.json) arrivano dal server come attributi data-* dell'input nascosto.
    const regimeWithoutVat = () => regimeSelect?.dataset.appliesVat === '0';
    const regimeFixedNature = () => regimeSelect?.dataset.fixedNature || '';
    const regimeDefaultReference = () => regimeSelect?.dataset.defaultReference || '';
    const regimeNoWithholding = () => regimeSelect?.dataset.withholding === '0';
    const beniForm = document.getElementById('beni-servizi-form');
    const documentDate = document.getElementById('data-documento');
    const dueDate = document.getElementById('data-scadenza');
    const documentNumberInput = document.getElementById('num-doc');
    const invoiceForm = document.getElementById('invoice-form');
    const saveInvoiceButton = document.getElementById('save-invoice-button');
    const invoiceSaveStatus = document.getElementById('invoice-save-status');
    const orderIdInput = document.getElementById('order-id');
    const paymentStatus = document.getElementById('stato-pagamento');
    const paymentTerms = document.getElementById('condizioni-pagamento');
    const paymentMethod = document.getElementById('modalita-pagamento');
    const paymentAmount = document.getElementById('importo-pagamento');
    const paymentReference = document.getElementById('riferimento-pagamento');
    const paymentReceived = document.getElementById('pagamento-effettuato');
    const paymentReceivedDetail = document.getElementById('pagamento-effettuato-dettaglio');
    let paymentAmountManual = false;
    const invoiceActions = document.querySelector('.invoice-actions');
    const invoiceCreateWorkflow = document.getElementById('invoice-create-workflow');
    const invoiceListView = document.getElementById('invoice-list-view');
    const invoiceList = document.getElementById('invoice-list');
    const invoiceListStatus = document.getElementById('invoice-list-status');
    let invoiceNumberRequest = 0;

    const loadPaymentMethods = () => {
        if (!paymentMethod) return;
        if (paymentMethod.dataset.paymentMethodsState) return;
        paymentMethod.dataset.paymentMethodsState = 'loading';
        paymentMethodsPromise ??= fetch('/assets/data/modalita-pagamento.json', { credentials: 'same-origin' })
            .then((response) => {
                if (!response.ok) throw new Error('Impossibile caricare le modalità di pagamento');
                return response.json();
            });
        paymentMethodsPromise.then((methods) => {
                paymentMethod.dataset.paymentMethodsState = 'loaded';
                paymentMethod.replaceChildren(new Option('Seleziona modalità', ''));
                methods.forEach((method) => paymentMethod.append(new Option(method.name, method.value)));
                paymentMethod.value = paymentMethod.dataset.preferred || 'MP05';
            })
            .catch((error) => {
                console.error(error);
                paymentMethod.dataset.paymentMethodsState = 'error';
                paymentMethod.replaceChildren(new Option('Modalità non disponibili', ''));
            });
    };

    const updatePaymentStatusDisplay = (order = null) => {
        const status = paymentStatus?.value || 'unpaid';
        if (paymentAmount) {
            paymentAmount.readOnly = status === 'paid';
            if (status === 'paid' && order) paymentAmount.value = '0.00';
        }
        if (paymentReceived) paymentReceived.hidden = status !== 'paid' && status !== 'partial';
        if (paymentReceivedDetail) {
            const paidAmount = Number(order?.paid_amount || 0);
            paymentReceivedDetail.textContent = paidAmount > 0
                ? `Incassato: ${paidAmount.toFixed(2)} ${order?.currency || 'EUR'}`
                : 'Nessun pagamento registrato';
        }
    };

    loadPaymentMethods();
    paymentStatus?.addEventListener('change', () => updatePaymentStatusDisplay());
    paymentAmount?.addEventListener('input', () => {
        paymentAmountManual = true;
    });

    const generateInvoiceNumber = () => {
        if (!documentNumberInput) return;
        const requestId = ++invoiceNumberRequest;
        const csrfToken = invoiceForm?.querySelector('[name="csrf_token"]')?.value || '';
        documentNumberInput.disabled = true;
        documentNumberInput.value = '';
        documentNumberInput.placeholder = 'Generazione numero...';

        fetch('/invoice/generate-invoice-number.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: new URLSearchParams({ csrf_token: csrfToken })
        })
            .then((response) => {
                if (!response.ok) throw new Error('Impossibile generare il numero documento');
                return response.json();
            })
            .then((data) => {
                if (requestId !== invoiceNumberRequest) return;
                if (!data.success || !data.document_number) {
                    throw new Error(data.message || 'Impossibile generare il numero documento');
                }
                documentNumberInput.value = data.document_number;
                documentNumberInput.disabled = false;
                documentNumberInput.placeholder = '';
                documentNumberInput.removeAttribute('aria-invalid');
            })
            .catch((error) => {
                if (requestId !== invoiceNumberRequest) return;
                console.error(error);
                documentNumberInput.placeholder = 'Numero non disponibile';
                documentNumberInput.setAttribute('aria-invalid', 'true');
            });
    };

    if (invoiceActions && invoiceActions.dataset.initialized !== 'true') {
        invoiceActions.dataset.initialized = 'true';
        let invoicesLoaded = false;
        let invoicesLoading = false;

        const showView = (view) => {
            invoiceActions.hidden = view !== 'home';
            invoiceListView.hidden = view !== 'browse';
            invoiceCreateWorkflow.hidden = view !== 'create';
        };

        const addDetail = (container, label, value) => {
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value || '—';
            container.append(term, description);
        };

        const displayValue = (value, currency) => {
            const amount = Number(value);
            return Number.isFinite(amount)
                ? new Intl.NumberFormat('it-IT', { style: 'currency', currency }).format(amount)
                : `${value} ${currency}`;
        };

        const formatDate = (dateString) => {
            if (!dateString) return '—';
            const parts = dateString.split('-');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            return dateString;
        };

        const renderInvoices = (invoices) => {
            invoiceList.replaceChildren();
            if (invoices.length === 0) {
                invoiceListStatus.textContent = 'Non hai ancora fatture salvate.';
                return;
            }

            invoiceListStatus.textContent = `${invoices.length} ${invoices.length === 1 ? 'fattura salvata' : 'fatture salvate'}`;
            invoices.forEach((invoice) => {
                const entry = document.createElement('details');
                entry.className = 'invoice-entry';
                const summary = document.createElement('summary');
                const icon = document.createElement('span');
                icon.className = 'invoice-entry__icon';
                icon.setAttribute('aria-hidden', 'true');
                icon.innerHTML = '<svg viewBox="0 0 24 24" focusable="false"><path d="M6 3.5h8l4 4v13H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2ZM14 3.5v5h5M8 13h8M8 17h8" /></svg>';
                const identity = document.createElement('span');
                identity.className = 'invoice-entry__identity';
                const title = document.createElement('strong');
                title.textContent = invoice.document_number;
                const subtitle = document.createElement('small');
                subtitle.textContent = `${invoice.client_name} · ${formatDate(invoice.issue_date)}`;
                identity.append(title, subtitle);
                const total = document.createElement('strong');
                total.className = 'invoice-entry__total';
                total.textContent = displayValue(invoice.total, invoice.currency);
                summary.append(icon, identity, total);
                entry.append(summary);

                const content = document.createElement('div');
                content.className = 'invoice-entry__details';
                const metadata = document.createElement('dl');
                metadata.className = 'invoice-metadata';
                addDetail(metadata, 'Regime fiscale', invoice.tax_regime_label || invoice.tax_regime);
                addDetail(metadata, 'Data documento', formatDate(invoice.issue_date));
                addDetail(metadata, 'Termine pagamento', formatDate(invoice.due_date));
                addDetail(metadata, 'Stato pagamento', invoice.is_paid ? 'Pagata' : 'Da pagare');
                if (invoice.bollo_to_client && Number(invoice.bollo) > 0) {
                    addDetail(metadata, 'Marca da bollo addebitata', displayValue(invoice.bollo, invoice.currency));
                }
                
                content.append(metadata);

                if ((invoice.causale || '').trim()) {
                    const causaleHeading = document.createElement('h3');
                    causaleHeading.textContent = 'Causale';
                    const causaleText = document.createElement('p');
                    causaleText.className = 'invoice-causale';
                    causaleText.textContent = invoice.causale;
                    content.append(causaleHeading, causaleText);
                }

                if (invoice.xml_available && invoice.items.length) {
                    const itemHeading = document.createElement('h3');
                    itemHeading.textContent = 'Beni e servizi';
                    const itemList = document.createElement('ul');
                    itemList.className = 'invoice-item-list';
                    invoice.items.forEach((item) => {
                        const row = document.createElement('li');
                        const description = document.createElement('span');
                        description.textContent = `${item.description} · ${item.quantity} × ${displayValue(item.unit_price, invoice.currency)} · IVA ${item.vat_rate}%`;
                        const lineTotal = document.createElement('strong');
                        lineTotal.textContent = displayValue(item.line_total, invoice.currency);
                        row.append(description, lineTotal);
                        itemList.append(row);
                    });
                    content.append(itemHeading, itemList);
                } else {
                    const fileStatus = document.createElement('p');
                    fileStatus.className = 'invoice-file-status';
                    fileStatus.textContent = invoice.xml_available
                        ? 'Il file XML archiviato non contiene righe leggibili.'
                        : 'File XML non disponibile nello storage.';
                    content.append(fileStatus);
                }

                if (invoice.cassa) {
                    const natureLabels = { non_soggetta: 'Non soggetta', esente: 'Esente', fuori_campo: 'Fuori campo IVA' };
                    const cassaHeading = document.createElement('h3');
                    cassaHeading.textContent = 'Cassa previdenziale';
                    const cassaList = document.createElement('ul');
                    cassaList.className = 'invoice-item-list';
                    const addCassaRow = (label, amount) => {
                        const row = document.createElement('li');
                        const text = document.createElement('span');
                        text.textContent = label;
                        row.append(text);
                        if (amount !== null) {
                            const value = document.createElement('strong');
                            value.textContent = displayValue(amount, invoice.currency);
                            row.append(value);
                        }
                        cassaList.append(row);
                    };
                    const cassa = invoice.cassa;
                    addCassaRow(
                        `${cassa.type_label || cassa.type} · ${cassa.rate}% su ${displayValue(cassa.taxable_amount, invoice.currency)}`,
                        cassa.amount
                    );
                    if (Number(cassa.vat_rate) > 0) {
                        addCassaRow(`IVA ${Number(cassa.vat_rate)}% sul contributo`, cassa.tax_amount);
                    } else if (cassa.nature) {
                        addCassaRow(`Contributo senza IVA · ${natureLabels[cassa.nature] || cassa.nature}`, null);
                    }
                    if (cassa.withholding === 'SI') addCassaRow('Concorre alla base della ritenuta', null);
                    if (cassa.reference) addCassaRow(`Riferimento amministrazione: ${cassa.reference}`, null);
                    content.append(cassaHeading, cassaList);
                }

                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'invoice-download-actions';
                actionsDiv.style.cssText = 'margin-top: 1.5rem; display: flex; gap: 1rem;';

                const pdfButton = document.createElement('a');
                pdfButton.className = 'profile-modal__save';
                pdfButton.style.textDecoration = 'none';
                pdfButton.textContent = 'Scarica PDF';
                pdfButton.href = `/invoice/download-invoice.php?id=${invoice.id}&format=pdf`;
                pdfButton.target = '_blank';

                const xmlButton = document.createElement('a');
                xmlButton.className = 'profile-modal__save';
                xmlButton.style.textDecoration = 'none';
                xmlButton.textContent = 'Scarica XML';
                xmlButton.href = `/invoice/download-invoice.php?id=${invoice.id}&format=xml`;
                xmlButton.target = '_blank';

                actionsDiv.append(pdfButton, xmlButton);
                content.append(actionsDiv);

                entry.append(content);
                invoiceList.append(entry);
            });
        };

        const loadInvoices = async () => {
            if (invoicesLoading) return;
            invoicesLoading = true;
            invoiceListStatus.textContent = 'Caricamento fatture...';
            try {
                const response = await fetch('/invoice/list-invoices.php', { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Impossibile caricare le fatture');
                renderInvoices(data.invoices);
                invoicesLoaded = true;
            } catch (error) {
                invoiceListStatus.textContent = error.message || 'Errore durante il caricamento delle fatture';
            } finally {
                invoicesLoading = false;
            }
        };

        invoiceActions.addEventListener('click', (event) => {
            const button = event.target.closest('[data-invoice-action]');
            if (!button) return;
            if (button.dataset.invoiceAction === 'browse') {
                showView('browse');
                if (!invoicesLoaded || button.dataset.refreshInvoices === 'true') {
                    button.dataset.refreshInvoices = 'false';
                    invoicesLoaded = false;
                    loadInvoices();
                }
                return;
            }
            if (button.dataset.invoiceAction === 'create') {
                showView('create');
                document.dispatchEvent(new Event('invoice:new'));
                return;
            }
            showView('home');
        });
        invoiceListView.querySelector('[data-invoice-action="home"]').addEventListener('click', () => showView('home'));
        invoiceCreateWorkflow.querySelector('[data-invoice-action="home"]').addEventListener('click', () => showView('home'));
        bindDocument('invoice-saved', 'invoice:saved', () => showView('home'));
        invoiceActions.hidden = false;
    }

    if (documentNumberInput && invoiceForm && documentNumberInput.dataset.initialized !== 'true') {
        documentNumberInput.dataset.initialized = 'true';
        generateInvoiceNumber();
    }

    if (invoiceForm && saveInvoiceButton && invoiceForm.dataset.saveInitialized !== 'true') {
        invoiceForm.dataset.saveInitialized = 'true';
        bindDocument('invoice-new-form', 'invoice:new', () => {
            invoiceForm.reset();
            paymentAmountManual = false;
            if (orderIdInput) orderIdInput.value = '';
            if (paymentMethod) {
                paymentMethod.value = 'MP05';
                delete paymentMethod.dataset.preferred;
            }
            if (paymentTerms) paymentTerms.value = 'TP02';
            if (paymentAmount) paymentAmount.value = '';
            updatePaymentStatusDisplay();
            invoiceSaveStatus.textContent = '';
            saveInvoiceButton.textContent = 'Salva fattura';
            saveInvoiceButton.disabled = false;
            if (dueDate && documentDate) dueDate.min = documentDate.value;
            documentNumberInput.removeAttribute('aria-invalid');
            generateInvoiceNumber();
        });
        invoiceForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            saveInvoiceButton.disabled = true;
            invoiceSaveStatus.textContent = 'Salvataggio fattura...';

            try {
                const response = await fetch(invoiceForm.action, {
                    method: 'POST',
                    body: new FormData(invoiceForm),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.document_number) documentNumberInput.value = data.document_number;
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Impossibile salvare la fattura');
                }

                invoiceSaveStatus.textContent = `Fattura salvata. XML: ${data.xml_file}`;
                saveInvoiceButton.textContent = 'Fattura salvata';
                const browseButton = document.querySelector('[data-invoice-action="browse"]');
                if (browseButton) browseButton.dataset.refreshInvoices = 'true';
                document.dispatchEvent(new Event('invoice:saved'));
            } catch (error) {
                invoiceSaveStatus.textContent = error.message || 'Errore durante il salvataggio della fattura';
                saveInvoiceButton.disabled = false;
            }
        });
    }

    if (documentDate && dueDate && dueDate.dataset.initialized !== 'true') {
        dueDate.dataset.initialized = 'true';
        const updateDueDateMinimum = () => {
            dueDate.min = documentDate.value;
            if (documentDate.value && dueDate.value && dueDate.value < documentDate.value) {
                dueDate.value = '';
            }
        };

        documentDate.addEventListener('input', updateDueDateMinimum);
        documentDate.addEventListener('change', updateDueDateMinimum);
        updateDueDateMinimum();
    }

    if (beniForm && regimeSelect && beniForm.dataset.initialized !== 'true') {
        beniForm.dataset.initialized = 'true';
        let nextBeniRowId = beniForm.querySelectorAll('[data-beni-row]').length + 1;
        const summaryList = document.querySelector('[data-vat-summary-list]');
        const summaryTemplate = document.querySelector('[data-vat-summary-template]');
        const bolloContainer = document.getElementById('bollo-container');
        const bolloCheckbox = document.getElementById('bollo-to-client');
        const invoiceTypeInput = document.getElementById('invoice-type');
        const invoiceTypeStatus = document.getElementById('invoice-type-status');

        const roundMoney = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

        // ---- Natura IVA (catalogo: /assets/data/natura.json) ----
        let natureCatalog = [];
        // Riempie un select con le Nature ammesse. Con restricted=true (regime senza IVA) resta solo la Natura imposta dal regime.
        const fillNatureOptions = (select, { restricted, placeholder = '' }) => {
            const previous = select.value;
            const fixed = regimeFixedNature();
            let entries = restricted ? natureCatalog.filter((entry) => entry.value === fixed) : natureCatalog;
            if (restricted && entries.length === 0 && fixed) {
                entries = [{ value: fixed, name: fixed }];
            }
            const options = entries.map((entry) => new Option(`${entry.value} – ${entry.name}`, entry.value));
            if (!restricted) options.unshift(new Option(placeholder || 'Seleziona la Natura', ''));
            select.replaceChildren(...options);
            if (options.some((option) => option.value === previous)) select.value = previous;
        };
        const getTaxableTotal = () => {
            let total = 0;
            beniForm.querySelectorAll('[data-beni-row]').forEach((row) => {
                const quantity = Number(row.querySelector('[name="quantita[]"]').value) || 0;
                const unitPrice = Number(row.querySelector('[name="prezzo_unitario[]"]').value) || 0;
                const discountType = row.querySelector('[name="tipo_sconto[]"]')?.value || 'none';
                const discountValue = Number(row.querySelector('[name="valore_sconto[]"]')?.value) || 0;
                const gross = quantity * unitPrice;
                total += discountType === 'percentage'
                    ? gross * (1 - Math.min(100, discountValue) / 100)
                    : discountType === 'amount' ? Math.max(0, gross - discountValue) : gross;
            });
            if (summaryList) {
                summaryList.querySelectorAll('[name="spese_accessorie[]"]').forEach((input) => {
                    total += Number(input.value) || 0;
                });
            }
            return roundMoney(total);
        };

        // ---- Cassa previdenziale (blocco DatiCassaPrevidenziale della FatturaPA) ----
        // Il server ricalcola tutto: questi campi servono solo a mostrare l'anteprima all'utente.
        const cassaForm = document.getElementById('cassa-form');
        const cassaSelect = document.getElementById('cassa-tipo');
        const cassaDetails = cassaForm ? cassaForm.querySelector('[data-cassa-details]') : null;
        const cassaRate = document.getElementById('cassa-aliquota');
        const cassaTaxable = document.getElementById('cassa-imponibile');
        const cassaVat = document.getElementById('cassa-aliquota-iva');
        const cassaNature = document.getElementById('cassa-natura');
        const cassaAmount = document.getElementById('cassa-importo');
        const cassaTax = document.getElementById('cassa-imposta');
        const cassaReady = Boolean(cassaSelect && cassaDetails && cassaRate && cassaTaxable && cassaVat
            && cassaNature && cassaAmount && cassaTax);
        const cassaCatalog = new Map();
        let cassaContribution = 0;
        const ritenutaForm = document.getElementById('ritenuta-form');
        const ritenutaType = document.getElementById('ritenuta-tipo');
        const ritenutaRate = document.getElementById('ritenuta-aliquota');
        const ritenutaAmount = document.getElementById('ritenuta-importo');
        const ritenuteCatalog = new Map();

        const updateRitenuta = () => {
            if (!ritenutaForm || !ritenutaType || !ritenutaRate || !ritenutaAmount) return;
            const enabled = !regimeNoWithholding() && ritenutaType.value !== '';
            ritenutaForm.hidden = regimeNoWithholding();
            ritenutaRate.disabled = !enabled;
            ritenutaRate.required = enabled;
            const rate = enabled ? Number(ritenutaRate.value) || 0 : 0;
            ritenutaAmount.value = roundMoney(getTaxableTotal() * rate / 100).toFixed(2);
        };

        if (ritenutaForm && ritenutaType && ritenutaRate) {
            fetch('/assets/data/ritenute.json')
                .then((response) => {
                    if (!response.ok) throw new Error('Impossibile caricare le ritenute');
                    return response.json();
                })
                .then((ritenute) => {
                    ritenute.forEach((ritenuta) => {
                        ritenuteCatalog.set(ritenuta.value, ritenuta);
                        ritenutaType.append(new Option(`${ritenuta.value} – ${ritenuta.name}`, ritenuta.value));
                    });
                    updateRitenuta();
                })
                .catch((error) => console.error(error));
            ritenutaType.addEventListener('change', () => {
                const selected = ritenuteCatalog.get(ritenutaType.value);
                const defaults = { RT01: 20, RT02: 0, RT03: 17, RT04: 19.5, RT05: 4 };
                ritenutaRate.value = selected ? (defaults[ritenutaType.value] ?? 0) : 0;
                updateRitenuta();
                updateInvoiceType();
            });
            ritenutaRate.addEventListener('input', () => {
                updateRitenuta();
                updateInvoiceType();
            });
        }

        const cassaGridFirst = cassaForm ? cassaForm.querySelector('.vat-summary-grid.cassa-grid') : null;
        const cassaManualLabel = cassaForm ? cassaForm.querySelector('[data-cassa-manual-label]') : null;
        const cassaManualInput = cassaForm ? cassaForm.querySelector('[data-cassa-manual-input]') : null;

        const updateCassa = () => {
            if (!cassaReady) {
                cassaContribution = 0;
                return;
            }
            const hasCassa = cassaSelect.value !== '';
            const entry = hasCassa ? cassaCatalog.get(cassaSelect.value) : null;
            const hasDefaultRate = entry && entry.default_rate != null;
            const needsManualRate = hasCassa && !hasDefaultRate;

            if (cassaGridFirst) {
                cassaGridFirst.classList.toggle('has-manual-rate', needsManualRate);
            }
            if (cassaManualLabel) cassaManualLabel.hidden = !needsManualRate;
            if (cassaManualInput) {
                cassaManualInput.hidden = !needsManualRate;
                cassaManualInput.required = needsManualRate;
            }

            const noVatRegime = regimeWithoutVat();
            cassaDetails.hidden = !hasCassa;
            cassaRate.required = hasCassa;
            cassaTaxable.required = hasCassa;

            if (!hasCassa) {
                cassaContribution = 0;
                cassaAmount.value = '';
                cassaTax.value = '';
                cassaRate.value = '';
                return;
            }

            if (hasDefaultRate) {
                cassaRate.value = Number(entry.default_rate).toFixed(2);
            }

            // IVA sul contributo: in regimi senza IVA sempre 0, in ordinario si ricorda la scelta (default 22).
            cassaVat.disabled = noVatRegime;
            cassaVat.value = noVatRegime ? '0' : (cassaVat.dataset.preferred || '22');
            const vatRate = Number(cassaVat.value) || 0;

            // Natura: richiesta solo con aliquota 0; in regimi senza IVA è fissa.
            if (vatRate > 0) {
                cassaNature.replaceChildren(new Option('Non applicabile', ''));
                cassaNature.disabled = true;
                cassaNature.required = false;
            } else if (noVatRegime) {
                fillNatureOptions(cassaNature, { restricted: true });
                cassaNature.value = regimeFixedNature();
                cassaNature.disabled = true;
                cassaNature.required = false;
            } else {
                fillNatureOptions(cassaNature, { restricted: false });
                cassaNature.disabled = false;
                cassaNature.required = true;
            }

            // L'imponibile segue le righe finché l'utente non lo modifica a mano.
            if (cassaTaxable.dataset.manual !== 'true') cassaTaxable.value = getTaxableTotal().toFixed(2);

            const rate = Number(cassaRate.value) || 0;
            const taxable = Number(cassaTaxable.value) || 0;
            cassaContribution = roundMoney(taxable * rate / 100);
            cassaAmount.value = cassaContribution.toFixed(2);
            cassaTax.value = roundMoney(cassaContribution * vatRate / 100).toFixed(2);
        };

        const refreshCassa = () => {
            updateCassa();
            updateRitenuta();
            updateBolloVisibility();
            updateInvoiceType();
        };

        if (cassaReady) {
            cassaSelect.disabled = true;
            fetch('/assets/data/casse-previdenziali.json')
                .then((response) => {
                    if (!response.ok) throw new Error('Impossibile caricare le casse previdenziali');
                    return response.json();
                })
                .then((casse) => {
                    cassaSelect.replaceChildren(new Option('Nessuna cassa previdenziale', ''));
                    casse.forEach((cassa) => {
                        cassaCatalog.set(cassa.value, cassa);
                        cassaSelect.append(new Option(`${cassa.value} – ${cassa.name}`, cassa.value));
                    });
                    cassaSelect.disabled = false;
                })
                .catch((error) => {
                    console.error(error);
                    cassaSelect.replaceChildren(new Option('Impossibile caricare le casse previdenziali', ''));
                });

            cassaForm.addEventListener('input', (event) => {
                if (!event.target.matches('input[type="number"]')) return;
                if (event.target === cassaTaxable) cassaTaxable.dataset.manual = 'true';
                refreshCassa();
            });
            cassaForm.addEventListener('change', (event) => {
                if (event.target === cassaSelect) {
                    const entry = cassaCatalog.get(cassaSelect.value);
                    if (entry && entry.default_rate != null) {
                        cassaRate.value = Number(entry.default_rate).toFixed(2);
                    } else {
                        cassaRate.value = '';
                    }
                    cassaTaxable.dataset.manual = 'false';
                } else if (event.target === cassaTaxable && cassaTaxable.value === '') {
                    cassaTaxable.dataset.manual = 'false';
                } else if (event.target === cassaVat && !regimeWithoutVat()) {
                    cassaVat.dataset.preferred = cassaVat.value;
                }
                refreshCassa();
            });
            if (invoiceForm) {
                invoiceForm.addEventListener('reset', () => {
                    cassaTaxable.dataset.manual = 'false';
                    delete cassaVat.dataset.preferred;
                    setTimeout(refreshCassa, 0);
                });
            }
        }

        const updateBolloVisibility = () => {
            if (!bolloContainer || !bolloCheckbox) return;
            const noVatRegime = regimeWithoutVat();
            const totalTaxable = getTaxableTotal() + cassaContribution;
            const showBollo = noVatRegime && roundMoney(totalTaxable) > 77.47;
            bolloContainer.hidden = !showBollo;
            if (!showBollo) {
                bolloCheckbox.checked = false;
            }
        };

        const getDocumentTotal = () => {
            let total = 0;
            beniForm.querySelectorAll('[data-beni-row]').forEach((row) => {
                const quantity = Number(row.querySelector('[name="quantita[]"]').value) || 0;
                const unitPrice = Number(row.querySelector('[name="prezzo_unitario[]"]').value) || 0;
                const rate = Number(row.querySelector('select[name="aliquota_iva[]"]').value) || 0;
                const discountType = row.querySelector('[name="tipo_sconto[]"]')?.value || 'none';
                const discountValue = Number(row.querySelector('[name="valore_sconto[]"]')?.value) || 0;
                const gross = quantity * unitPrice;
                const taxable = discountType === 'percentage'
                    ? gross * (1 - Math.min(100, discountValue) / 100)
                    : discountType === 'amount' ? Math.max(0, gross - discountValue) : gross;
                total += taxable * (1 + rate / 100);
            });
            summaryList?.querySelectorAll('[name="spese_accessorie[]"]').forEach((input) => {
                const row = input.closest('[data-vat-summary-row]');
                const rate = Number(row?.querySelector('[data-summary-rate]')?.value) || 0;
                const expense = Number(input.value) || 0;
                total += expense * (1 + rate / 100);
            });
            summaryList?.querySelectorAll('[name="spese_accessorie_non_imponibili[]"]').forEach((input) => {
                total += Number(input.value) || 0;
            });
            total += cassaContribution;
            if (cassaTax) total += Number(cassaTax.value) || 0;
            if (bolloCheckbox?.checked) total += 2;
            if (ritenutaAmount) total -= Number(ritenutaAmount.value) || 0;
            const documentTotal = roundMoney(total);
            if (paymentAmount && !paymentAmountManual && !(orderIdInput?.value)) {
                paymentAmount.value = documentTotal.toFixed(2);
            }
            return documentTotal;
        };

        const updateInvoiceType = () => {
            if (!invoiceTypeInput || !invoiceTypeStatus) return;
            const hasCassa = Boolean(cassaSelect?.value);
            const hasRitenuta = Boolean(ritenutaType?.value);
            const simplified = getDocumentTotal() <= 400 && !hasCassa && !hasRitenuta;
            invoiceTypeInput.value = simplified ? 'semplificata' : 'ordinaria';
            invoiceTypeStatus.innerHTML = simplified
                ? '<strong>Fattura semplificata</strong> (default; ordinaria oltre € 400,00)'
                : hasCassa
                    ? '<strong>Fattura ordinaria</strong> (necessaria per la cassa previdenziale)'
                    : '<strong>Fattura ordinaria</strong> (totale documento superiore a € 400,00)';
            if (ritenutaForm) {
                ritenutaForm.hidden = regimeNoWithholding();
            }
        };

        beniForm.querySelectorAll('[data-beni-row]').forEach((itemRow, index) => {
            itemRow.dataset.vatSummaryId = `item-${index + 1}`;
        });
        const updateSummary = () => {
            if (!summaryList || !summaryTemplate) return;

            const previousValues = new Map([...summaryList.children].map((summaryRow) => [
                summaryRow.dataset.vatSummaryId,
                {
                    nature: summaryRow.querySelector('[data-summary-nature]').value,
                    expenses: summaryRow.querySelector('[name="spese_accessorie[]"]').value,
                    nonTaxableExpenses: summaryRow.querySelector('[name="spese_accessorie_non_imponibili[]"]').value,
                    reference: summaryRow.querySelector('[name="riferimento_normativo[]"]').value
                }
            ]));
            summaryList.replaceChildren();
            beniForm.querySelectorAll('[data-beni-row]').forEach((itemRow, index) => {
                const description = itemRow.querySelector('[name="descrizione[]"]').value.trim();
                const quantity = Number(itemRow.querySelector('[name="quantita[]"]').value) || 0;
                const unitPrice = Number(itemRow.querySelector('[name="prezzo_unitario[]"]').value) || 0;
                const rate = Number(itemRow.querySelector('select[name="aliquota_iva[]"]').value) || 0;
                const discountType = itemRow.querySelector('[name="tipo_sconto[]"]')?.value || 'none';
                const discountValue = Number(itemRow.querySelector('[name="valore_sconto[]"]')?.value) || 0;
                const gross = quantity * unitPrice;
                const taxable = discountType === 'percentage'
                    ? gross * (1 - Math.min(100, discountValue) / 100)
                    : discountType === 'amount' ? Math.max(0, gross - discountValue) : gross;
                const row = summaryTemplate.content.firstElementChild.cloneNode(true);
                const summaryId = itemRow.dataset.vatSummaryId;
                const rowId = `vat-${summaryId}`;
                const previous = previousValues.get(summaryId);
                const natureSelect = row.querySelector('[data-summary-nature]');
                const natureValue = row.querySelector('[data-summary-nature-value]');
                const noVatRegime = regimeWithoutVat();
                const itemTitle = description || `Beni e Servizi ${index + 1}`;
                row.dataset.vatSummaryId = summaryId;
                row.querySelector('[data-vat-summary-title]').textContent = `Riepilogo importi per aliquota - ${itemTitle}`;
                row.querySelector('[data-summary-rate]').id = `${rowId}-rate`;
                natureSelect.id = `${rowId}-nature`;
                row.querySelector('[data-summary-taxable]').id = `${rowId}-taxable`;
                row.querySelector('[data-summary-tax]').id = `${rowId}-tax`;
                row.querySelector('[name="spese_accessorie[]"]').id = `${rowId}-expenses`;
                row.querySelector('[name="spese_accessorie_non_imponibili[]"]').id = `${rowId}-non-taxable-expenses`;
                row.querySelector('[name="riferimento_normativo[]"]').id = `${rowId}-reference`;
                if (rate > 0) {
                    natureSelect.replaceChildren(new Option('Non applicabile', ''));
                    natureSelect.disabled = true;
                } else if (noVatRegime) {
                    fillNatureOptions(natureSelect, { restricted: true });
                    natureSelect.value = regimeFixedNature();
                    natureSelect.disabled = true;
                } else {
                    fillNatureOptions(natureSelect, { restricted: false });
                    natureSelect.value = previous?.nature || '';
                    natureSelect.disabled = false;
                }
                natureValue.value = natureSelect.value;
                row.querySelector('[name="spese_accessorie[]"]').value = previous?.expenses || '0.00';
                row.querySelector('[name="spese_accessorie_non_imponibili[]"]').value = previous?.nonTaxableExpenses || '0.00';
                const defaultReference = noVatRegime && rate === 0 ? regimeDefaultReference() : '';
                const keepPrevious = previous?.reference && previous.reference !== regimeDefaultReference();
                row.querySelector('[name="riferimento_normativo[]"]').value = keepPrevious ? previous.reference : defaultReference;
                row.querySelectorAll('[data-summary-label]').forEach((label) => {
                    label.htmlFor = `${rowId}-${label.dataset.summaryLabel}`;
                });
                row.querySelector('[data-summary-rate]').value = rate;
                row.querySelector('[data-summary-taxable]').value = taxable.toFixed(2);
                const taxableExpenses = Number(row.querySelector('[name="spese_accessorie[]"]').value) || 0;
                row.querySelector('[data-summary-tax]').value = ((taxable + taxableExpenses) * (rate / 100)).toFixed(2);
                summaryList.append(row);
            });
            updateSummaryTotal();
            refreshCassa();
        };
        const updateSummaryTax = (summaryRow) => {
            const rate = Number(summaryRow.querySelector('[data-summary-rate]')?.value) || 0;
            const taxable = Number(summaryRow.querySelector('[data-summary-taxable]')?.value) || 0;
            const taxableExpenses = Number(summaryRow.querySelector('[name="spese_accessorie[]"]')?.value) || 0;
            const taxInput = summaryRow.querySelector('[data-summary-tax]');
            if (taxInput) {
                taxInput.value = ((taxable + taxableExpenses) * (rate / 100)).toFixed(2);
            }
            updateSummaryTotal();
        };
        const updateSummaryTotal = () => {
            const totalElement = document.querySelector('[data-vat-summary-total] strong');
            if (!totalElement) return;
            const total = [...(summaryList?.querySelectorAll('[data-summary-tax]') || [])]
                .reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            totalElement.textContent = total.toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };
        summaryList?.addEventListener('input', (event) => {
            if (event.target.matches('[name="spese_accessorie[]"], [name="spese_accessorie_non_imponibili[]"]')) {
                const summaryRow = event.target.closest('[data-vat-summary-row]');
                if (summaryRow) updateSummaryTax(summaryRow);
                refreshCassa();
            }
        });
        summaryList?.addEventListener('change', (event) => {
            if (!event.target.matches('[data-summary-nature]')) return;
            event.target.closest('[data-vat-summary-row]')
                .querySelector('[data-summary-nature-value]').value = event.target.value;
        });
        const updateTotal = (row) => {
            const quantity = Number(row.querySelector('[name="quantita[]"]').value) || 0;
            const unitPrice = Number(row.querySelector('[name="prezzo_unitario[]"]').value) || 0;
            const aliquotaIVA = Number(row.querySelector('select[name="aliquota_iva[]"]').value) || 0;
            const discountType = row.querySelector('[name="tipo_sconto[]"]')?.value || 'none';
            const discountValue = Number(row.querySelector('[name="valore_sconto[]"]')?.value) || 0;
            const gross = quantity * unitPrice;
            const imponibile = discountType === 'percentage'
                ? gross * (1 - Math.min(100, discountValue) / 100)
                : discountType === 'amount' ? Math.max(0, gross - discountValue) : gross;
            const iva = imponibile * (aliquotaIVA / 100);
            const totale = imponibile + iva;
            row.querySelector('[name="prezzo_totale[]"]').value = totale.toFixed(2);
        };

        const updateIva = () => {
            const noVatRegime = regimeWithoutVat();
            beniForm.querySelectorAll('select[name="aliquota_iva[]"]').forEach((ivaSelect) => {
                const row = ivaSelect.closest('[data-beni-row]');
                ivaSelect.querySelectorAll('option').forEach((option) => {
                    option.disabled = noVatRegime ? option.value !== '0' : option.value === '0';
                    option.hidden = !noVatRegime && option.value === '0';
                });

                const preferred = row.dataset.preferredRate;
                const preferredIsValid = ['4', '5', '10', '22'].includes(preferred);
                ivaSelect.value = noVatRegime ? '0' : (preferredIsValid ? preferred : '22');
                ivaSelect.disabled = noVatRegime;

                const hiddenIvaInput = row.querySelector('[data-aliquota-iva-value]');
                hiddenIvaInput.value = ivaSelect.value;
                hiddenIvaInput.disabled = !noVatRegime;
                updateTotal(row);
            });
            updateSummary();
        };

        beniForm.addEventListener('input', (event) => {
            const row = event.target.closest('[data-beni-row]');
            if (row && ['descrizione[]', 'quantita[]', 'prezzo_unitario[]', 'tipo_sconto[]', 'valore_sconto[]'].includes(event.target.name)) {
                if (event.target.name !== 'descrizione[]') updateTotal(row);
                updateSummary();
            }
        });
        beniForm.addEventListener('change', (event) => {
            if (event.target.matches('select[name="aliquota_iva[]"]')) {
                const row = event.target.closest('[data-beni-row]');
                if (!regimeWithoutVat()) {
                    row.dataset.preferredRate = event.target.value;
                }
                const hiddenIvaInput = row.querySelector('[data-aliquota-iva-value]');
                hiddenIvaInput.value = event.target.value;
                updateTotal(row);
                updateSummary();
            }
        });
        beniForm.addEventListener('click', (event) => {
            const removeControl = event.target.closest('[data-remove-beni]');
            if (removeControl) {
                removeControl.closest('[data-beni-row]').remove();
                updateSummary();
                return;
            }

            if (!event.target.closest('[data-add-beni]')) return;
            const row = event.target.closest('[data-beni-row]');
            const newRow = row.cloneNode(true);
            const newRowId = nextBeniRowId;
            nextBeniRowId += 1;
            newRow.dataset.vatSummaryId = `item-${newRowId}`;
            delete newRow.dataset.preferredRate;
            newRow.querySelector('[data-add-beni]').remove();
            newRow.querySelectorAll('input[id], select[id]').forEach((field) => {
                const baseId = field.id.replace(/-\d+$/, '');
                field.id = `${baseId}-${newRowId}`;
            });
            newRow.querySelectorAll('label[for]').forEach((label) => {
                const baseId = label.htmlFor.replace(/-\d+$/, '');
                label.htmlFor = `${baseId}-${newRowId}`;
            });
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'remove-beni-button';
            removeButton.dataset.removeBeni = '';
            removeButton.setAttribute('aria-label', 'Elimina beni e servizi');
            removeButton.textContent = 'X';
            newRow.querySelector('.form-beni-heading').append(removeButton);
            newRow.querySelectorAll('input').forEach((input) => {
                if (input.type === 'hidden') return;
                input.value = input.readOnly ? '0.00' : '';
            });
            newRow.querySelector('[name="aliquota_iva[]"]').value = '0';
            row.after(newRow);
            updateIva();
        });
        fetch('/assets/data/natura.json')
            .then((response) => {
                if (!response.ok) throw new Error('Impossibile caricare le nature IVA');
                return response.json();
            })
            .then((catalog) => {
                natureCatalog = Array.isArray(catalog) ? catalog : [];
                updateSummary();
            })
            .catch((error) => console.error(error));
        regimeSelect.addEventListener('change', updateIva);
        beniForm.querySelectorAll('[data-beni-row]').forEach(updateTotal);
        updateIva();
        updateSummary();
        bindDocument('invoice-new-beni', 'invoice:new', () => {
            const rows = [...beniForm.querySelectorAll('[data-beni-row]')];
            rows.slice(1).forEach((row) => row.remove());
            if (rows[0]) {
                rows[0].dataset.vatSummaryId = 'item-1';
                delete rows[0].dataset.preferredRate;
            }
            nextBeniRowId = 2;
            summaryList?.replaceChildren();
            updateIva();
        });
    }

    if (countrySelect && countrySelect.dataset.initialized !== 'true') {
        countrySelect.dataset.initialized = 'true';
        const selectedCountry = countrySelect.dataset.selectedCountry || '';
        countrySelect.disabled = true;
        fetch('/assets/data/countries.json')
            .then((response) => {
                if (!response.ok) throw new Error('Impossibile caricare gli Stati');
                return response.json();
            })
            .then((countries) => {
                countrySelect.replaceChildren(new Option('Seleziona uno Stato', '', selectedCountry === '', selectedCountry === ''));
                countries.forEach((country) => countrySelect.append(new Option(country.name, country.code)));
                const savedCountry = countries.find((country) =>
                    country.code.toLowerCase() === selectedCountry.trim().toLowerCase()
                    || country.name.toLowerCase() === selectedCountry.trim().toLowerCase()
                );
                countrySelect.value = savedCountry?.code || '';
                countrySelect.disabled = false;
            })
            .catch((error) => {
                console.error(error);
                countrySelect.replaceChildren(new Option('Impossibile caricare gli Stati', ''));
            });
    }

    if (currencySelect && currencySelect.dataset.initialized !== 'true') {
        currencySelect.dataset.initialized = 'true';
        currencySelect.disabled = true;
        fetch('/assets/data/currency.json')
            .then((response) => {
                if (!response.ok) throw new Error('Impossibile caricare le valute');
                return response.json();
            })
            .then((currencies) => {
                currencySelect.replaceChildren(new Option('Seleziona una divisa', ''));
                currencies.forEach((currency) => {
                    currencySelect.append(new Option(
                        `${currency.value} - ${currency.name} (${currency.symbol})`,
                        currency.value
                    ));
                });
                currencySelect.value = 'EUR';
                currencySelect.disabled = false;
            })
            .catch((error) => {
                console.error(error);
                currencySelect.replaceChildren(new Option('Impossibile caricare le valute', ''));
            });
    }

    if (!clientCompletion || clientCompletion.dataset.initialized === 'true') {
        if (clientCompletion && profileForm && profileForm.dataset.initialized !== 'true') {
            profileForm.dataset.initialized = 'true';
            profileForm.addEventListener('submit', (event) => {
                event.preventDefault();
                document.getElementById('profile-completion').hidden = true;
                clientCompletion.hidden = false;
            });
        }
        return;
    }

    if (!clientForm || !clientSelect || !useClientButton || !clientSelectPanel || !billingForm) {
        return;
    }

    clientCompletion.dataset.initialized = 'true';
    const clientsUrl = '/invoice/process-client.php';
    const clients = new Map();
    const orderSelect = document.getElementById('order-select');
    const clientOrders = new Map();
    const fields = ['id', 'client_type', 'company_name', 'first_name', 'last_name', 'vat_number', 'cf', 'address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'crm_status'];
    const modeButton = (mode) => clientCompletion.querySelector(`[data-client-mode="${mode}"]`);
    const setEditButtonDisabled = (disabled) => {
        const editButton = modeButton('edit');
        if (editButton) editButton.disabled = disabled;
    };

    const loadOrdersForClient = (clientId) => {
        if (!orderSelect) return;
        clientOrders.clear();

        if (!clientId) {
            orderSelect.replaceChildren(new Option('Seleziona prima un cliente', ''));
            return;
        }

        orderSelect.replaceChildren(new Option('Caricamento ordini...', ''));

        fetch(`${clientsUrl}?action=orders&client_id=${encodeURIComponent(clientId)}`, { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) throw new Error(data.message || 'Impossibile caricare gli ordini');
                const orders = (data.orders || []).filter((order) => !order.status || order.status === 'confirmed');
                if (orders.length === 0) {
                    orderSelect.replaceChildren(new Option('Nessun ordine disponibile per questo cliente', ''));
                    return;
                }
                orderSelect.replaceChildren(new Option('Seleziona un ordine', ''));
                orders.forEach((order) => {
                    clientOrders.set(String(order.id), order);
                    const label = `Ordine ${order.order_number} (${order.created_at}) - Totale: ${order.total} ${order.currency}`;
                    orderSelect.append(new Option(label, String(order.id)));
                });
            })
            .catch((error) => {
                console.error(error);
                orderSelect.replaceChildren(new Option('Errore caricamento ordini', ''));
            });
    };

    const showBilling = () => {
        clientCompletion.hidden = true;
        billingForm.hidden = false;
    };

    const updateClientTypeFields = () => {
        const isIndividual = clientForm.elements.client_type.value === 'individual';
        clientCompletion.querySelectorAll('[data-company-field]').forEach((field) => {
            field.hidden = isIndividual;
            field.style.display = isIndividual ? 'none' : '';
        });
        clientCompletion.querySelectorAll('[data-individual-field]').forEach((field) => {
            field.hidden = !isIndividual;
            field.style.display = isIndividual ? '' : 'none';
        });
        clientForm.elements.company_name.required = !isIndividual;
        clientForm.elements.first_name.required = isIndividual;
        clientForm.elements.last_name.required = isIndividual;
        clientForm.elements.cf.required = isIndividual;
    };

    const fillClientForm = (client) => {
        fields.forEach((field) => {
            if (clientForm.elements[field]) {
                clientForm.elements[field].value = client ? (client[field] || '') : '';
            }
        });
        if (!client) {
            clientForm.elements.id.value = '';
            clientForm.elements.client_type.value = 'company';
            clientForm.elements.country.value = 'Italia';
            if (clientForm.elements.crm_status) clientForm.elements.crm_status.value = 'lead';
        }
        updateClientTypeFields();
    };

    const setMode = (mode) => {
        clientModeButtons.forEach((button) => {
            const active = button.dataset.clientMode === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        clientSelectPanel.hidden = mode !== 'select';
        clientForm.hidden = mode === 'select';

        if (mode === 'add') {
            fillClientForm(null);
        }
        if (mode === 'edit') {
            const selected = clients.get(clientSelect.value);
            if (selected) fillClientForm(selected);
        }
        updateClientTypeFields();
    };

    const populateClients = (clientList) => {
        clientSelect.replaceChildren(new Option('Seleziona un cliente', ''));
        clients.clear();
        clientList.filter((client) => client.crm_status === 'customer').forEach((client) => {
            const name = client.client_type === 'company'
                ? client.company_name
                : `${client.first_name} ${client.last_name}`.trim();
            clients.set(String(client.id), client);
            clientSelect.append(new Option(name || `Cliente ${client.id}`, String(client.id)));
        });
    };

    const persistSelectedClient = (clientId) => {
        const csrfToken = clientForm.querySelector('[name="csrf_token"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'select_client');
        formData.append('client_id', String(clientId));
        formData.append('csrf_token', csrfToken);

        return fetch(clientsUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    console.warn(data.message || 'Impossibile salvare la selezione del cliente');
                }
                return data;
            });
    };

    const loadClients = () => fetch(clientsUrl, { credentials: 'same-origin' })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) throw new Error(data.message || 'Impossibile caricare i clienti');
            populateClients(data.clients);
        })
        .catch((error) => {
            console.error(error);
            clientSelect.replaceChildren(new Option('Nessun cliente disponibile', ''));
        });

    const applyOrderToInvoice = (order) => {
        if (orderIdInput) orderIdInput.value = String(order.id || '');
        if (paymentStatus) paymentStatus.value = order.payment_status || 'unpaid';
        if (paymentTerms) paymentTerms.value = order.payment_status === 'partial' ? 'TP01' : 'TP02';
        if (paymentMethod && order.last_payment_method) {
            paymentMethod.value = order.last_payment_method;
            paymentMethod.dataset.preferred = order.last_payment_method;
        }
        if (paymentReference) paymentReference.value = order.last_payment_reference || '';
        if (paymentAmount) {
            const total = regimeSelect?.value === 'RF19'
                ? Number(order.total_without_vat || 0)
                : Number(order.total || 0);
            const paid = Number(order.paid_amount || 0);
            paymentAmount.value = Math.max(0, total - paid).toFixed(2);
            paymentAmountManual = false;
        }
        updatePaymentStatusDisplay(order);

        const causaleField = document.getElementById('causale');
        if (causaleField) causaleField.value = order.notes || '';

        if (currencySelect) {
            currencySelect.value = order.currency || 'EUR';
            currencySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const items = order.items || [];
        if (!beniForm || items.length === 0) return;

        const hasVat = items.some((item) => parseInt(item.vat_rate, 10) > 0);
        if (hasVat && regimeSelect && regimeWithoutVat() && invoiceSaveStatus) {
            invoiceSaveStatus.textContent = 'Attenzione: l\'ordine contiene righe con IVA, ma il regime fiscale del cedente non prevede l\'IVA. L\'IVA verrà azzerata nella fattura.';
        }

        const getRows = () => [...beniForm.querySelectorAll('[data-beni-row]')];

        getRows().slice(1).forEach((row) => row.remove());
        const addButton = beniForm.querySelector('[data-add-beni]');
        for (let i = 1; i < items.length; i += 1) {
            if (addButton) addButton.click();
        }

        getRows().forEach((row, index) => {
            const item = items[index];
            if (!item) return;

            row.dataset.preferredRate = String(parseInt(item.vat_rate, 10) || 0);

            const descInput = row.querySelector('[name="descrizione[]"]');
            const qtyInput = row.querySelector('[name="quantita[]"]');
            const priceInput = row.querySelector('[name="prezzo_unitario[]"]');
            const discountTypeInput = row.querySelector('[name="tipo_sconto[]"]');
            const discountValueInput = row.querySelector('[name="valore_sconto[]"]');
            const ivaSelect = row.querySelector('select[name="aliquota_iva[]"]');

            if (descInput) descInput.value = item.description || '';
            if (qtyInput) qtyInput.value = String(parseFloat(item.quantity) || 1);
            if (priceInput) priceInput.value = String(parseFloat(item.unit_price) || 0);
            if (discountTypeInput) discountTypeInput.value = item.discount_type || 'none';
            if (discountValueInput) discountValueInput.value = String(parseFloat(item.discount_value) || 0);

            if (ivaSelect) {
                const wantedRate = String(parseInt(item.vat_rate, 10) || 0);
                const matchingOption = [...ivaSelect.options].find(
                    (option) => option.value === wantedRate && !option.disabled
                );
                if (matchingOption) ivaSelect.value = wantedRate;
                ivaSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            [descInput, qtyInput, priceInput].forEach((el) => {
                if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    };

    bindDocument('profile-saved', 'profile:saved', (event) => {
        if (!event.detail || !event.detail.fiscalComplete) return;
        window.__fiscalProfileComplete = true;
        const profileCompletion = document.getElementById('profile-completion');
        if (profileCompletion) profileCompletion.hidden = true;
        if (billingForm && billingForm.hidden) clientCompletion.hidden = false;
    });
    bindDocument('profile-open', 'click', (event) => {
        if (!event.target.closest('[data-open-profile-modal]')) return;
        const trigger = document.querySelector('.dashboard-navbar__user-trigger, [data-profile-modal-open]');
        if (trigger) trigger.click();
    });

    if (!clientForm || !clientSelect || !useClientButton || !clientCompletion || !billingForm) return;

    loadClients();
    bindDocument('invoice-new-client', 'invoice:new', () => {
        const profileCompletion = document.getElementById('profile-completion');
        const needsProfile = Boolean(profileCompletion && window.__fiscalProfileComplete !== true);
        if (profileCompletion) profileCompletion.hidden = !needsProfile;
        clientCompletion.hidden = needsProfile;
        billingForm.hidden = true;
        clientSelect.value = '';
        useClientButton.disabled = true;
        if (orderSelect) {
            orderSelect.value = '';
            orderSelect.replaceChildren(new Option('Seleziona un ordine', ''));
        }
        clientOrders.clear();
        setEditButtonDisabled(true);
        fillClientForm(null);
        setMode('select');
        clientSelect.replaceChildren(new Option('Caricamento clienti...', ''));
        loadClients();
    });

    clientSelect.addEventListener('change', () => {
        const clientId = clientSelect.value;
        const hasClient = clientId !== '';
        useClientButton.disabled = !hasClient;
        setEditButtonDisabled(!hasClient);
        if (orderSelect) {
            orderSelect.value = '';
        }
        loadOrdersForClient(clientId);
    });

    clientModeButtons.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.clientMode));
    });

    clientForm.elements.client_type.addEventListener('change', updateClientTypeFields);
    useClientButton.addEventListener('click', () => {
        const selectedClientId = clientSelect.value;
        if (!selectedClientId) {
            return;
        }

        persistSelectedClient(selectedClientId).finally(() => {
            const selectedOrderId = orderSelect ? orderSelect.value : '';
            if (selectedOrderId && clientOrders.has(selectedOrderId)) {
                applyOrderToInvoice(clientOrders.get(selectedOrderId));
            }

            showBilling();
        });
    });

    clientForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(clientForm);
        formData.append('action', 'save');

        fetch(clientsUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    alert(data.message || 'Impossibile salvare il cliente');
                    return;
                }

                persistSelectedClient(data.id).finally(() => showBilling());
            })
            .catch(() => alert('Errore durante il salvataggio del cliente'));
    });

    updateClientTypeFields();
    setMode('select');

    if (profileForm && profileForm.dataset.initialized !== 'true') {
        profileForm.dataset.initialized = 'true';
        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitButton = profileForm.querySelector('[type="submit"]');
            const status = document.getElementById('profile-completion-status');
            submitButton.disabled = true;
            status.textContent = 'Salvataggio profilo...';

            try {
                const response = await fetch('/auth/process-profile.php', {
                    method: 'POST',
                    body: new FormData(profileForm),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Impossibile salvare il profilo');
                }

                profileForm.dataset.completed = 'true';
                document.getElementById('profile-completion').hidden = true;
                clientCompletion.hidden = false;
            } catch (error) {
                status.textContent = error.message || 'Errore durante il salvataggio del profilo';
                submitButton.disabled = false;
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', initializeFatturazione);

let initScheduled = false;
const contentObserver = new MutationObserver(() => {
    if (initScheduled) return;
    initScheduled = true;
    requestAnimationFrame(() => {
        initScheduled = false;
        initializeFatturazione();
    });
});
contentObserver.observe(document.body, { childList: true, subtree: true });
