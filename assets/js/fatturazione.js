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
    const beniForm = document.getElementById('beni-servizi-form');
    const beniCheckbox = document.getElementById('beni');
    const documentDate = document.getElementById('data-documento');
    const dueDate = document.getElementById('data-scadenza');
    const documentNumberInput = document.getElementById('num-doc');
    const invoiceForm = document.getElementById('invoice-form');
    const saveInvoiceButton = document.getElementById('save-invoice-button');
    const invoiceSaveStatus = document.getElementById('invoice-save-status');
    const invoiceActions = document.querySelector('.invoice-actions');
    const invoiceCreateWorkflow = document.getElementById('invoice-create-workflow');
    const invoiceListView = document.getElementById('invoice-list-view');
    const invoiceList = document.getElementById('invoice-list');
    const invoiceListStatus = document.getElementById('invoice-list-status');
    let invoiceNumberRequest = 0;

    const generateInvoiceNumber = () => {
        const requestId = ++invoiceNumberRequest;
        const csrfToken = invoiceForm?.querySelector('[name="csrf_token"]')?.value || '';
        documentNumberInput.disabled = true;
        documentNumberInput.value = '';
        documentNumberInput.placeholder = 'Generazione numero...';

        fetch('/invoice/generate-invoice-number.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                subtitle.textContent = `${invoice.client_name} · ${invoice.issue_date}`;
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
                addDetail(metadata, 'Cliente', invoice.client_name);
                addDetail(metadata, 'Data documento', invoice.issue_date);
                addDetail(metadata, 'Scadenza', invoice.due_date);
                addDetail(metadata, 'Regime fiscale', invoice.tax_regime);
                addDetail(metadata, 'Stato pagamento', invoice.is_paid ? 'Pagata' : 'Da pagare');
                addDetail(metadata, 'Totale', displayValue(invoice.total, invoice.currency));
                content.append(metadata);

                if (invoice.causale.trim()) {
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
        document.addEventListener('invoice:saved', () => showView('home'));
        invoiceActions.hidden = false;
    }

    if (documentNumberInput && invoiceForm && documentNumberInput.dataset.initialized !== 'true') {
        documentNumberInput.dataset.initialized = 'true';
        generateInvoiceNumber();
    }

    if (invoiceForm && saveInvoiceButton && invoiceForm.dataset.saveInitialized !== 'true') {
        invoiceForm.dataset.saveInitialized = 'true';
        document.addEventListener('invoice:new', () => {
            invoiceForm.reset();
            invoiceSaveStatus.textContent = '';
            saveInvoiceButton.textContent = 'Salva fattura';
            saveInvoiceButton.disabled = false;
            dueDate.min = documentDate.value;
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

    if (beniForm && beniForm.dataset.initialized !== 'true') {
        beniForm.dataset.initialized = 'true';
        let nextBeniRowId = beniForm.querySelectorAll('[data-beni-row]').length + 1;
        const summaryList = document.querySelector('[data-vat-summary-list]');
        const summaryTemplate = document.querySelector('[data-vat-summary-template]');
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
                    reference: summaryRow.querySelector('[name="riferimento_normativo[]"]').value
                }
            ]));
            summaryList.replaceChildren();
            beniForm.querySelectorAll('[data-beni-row]').forEach((itemRow, index) => {
                const description = itemRow.querySelector('[name="descrizione[]"]').value.trim();
                const quantity = Number(itemRow.querySelector('[name="quantita[]"]').value) || 0;
                const unitPrice = Number(itemRow.querySelector('[name="prezzo_unitario[]"]').value) || 0;
                const rate = Number(itemRow.querySelector('select[name="aliquota_iva[]"]').value) || 0;
                const taxable = quantity * unitPrice;
                const row = summaryTemplate.content.firstElementChild.cloneNode(true);
                const summaryId = itemRow.dataset.vatSummaryId;
                const rowId = `vat-${summaryId}`;
                const previous = previousValues.get(summaryId);
                const natureSelect = row.querySelector('[data-summary-nature]');
                const natureValue = row.querySelector('[data-summary-nature-value]');
                const isForfettario = regimeSelect.value === 'regime forfettario';
                const itemTitle = description || `Beni e Servizi ${index + 1}`;
                row.dataset.vatSummaryId = summaryId;
                row.querySelector('[data-vat-summary-title]').textContent = `Riepilogo importi per aliquota - ${itemTitle}`;
                row.querySelector('[data-summary-rate]').id = `${rowId}-rate`;
                natureSelect.id = `${rowId}-nature`;
                row.querySelector('[data-summary-taxable]').id = `${rowId}-taxable`;
                row.querySelector('[data-summary-tax]').id = `${rowId}-tax`;
                row.querySelector('[name="spese_accessorie[]"]').id = `${rowId}-expenses`;
                row.querySelector('[name="riferimento_normativo[]"]').id = `${rowId}-reference`;
                natureSelect.value = isForfettario ? 'non_soggetta' : (previous?.nature || 'non_soggetta');
                natureSelect.disabled = isForfettario;
                natureValue.value = natureSelect.value;
                row.querySelector('[name="spese_accessorie[]"]').value = previous?.expenses || '0.00';
                row.querySelector('[name="riferimento_normativo[]"]').value = previous?.reference || '';
                row.querySelectorAll('[data-summary-label]').forEach((label) => {
                    label.htmlFor = `${rowId}-${label.dataset.summaryLabel}`;
                });
                row.querySelector('[data-summary-rate]').value = rate;
                row.querySelector('[data-summary-taxable]').value = taxable.toFixed(2);
                row.querySelector('[data-summary-tax]').value = (taxable * (rate / 100)).toFixed(2);
                summaryList.append(row);
            });
        };
        summaryList.addEventListener('change', (event) => {
            if (!event.target.matches('[data-summary-nature]')) return;
            event.target.closest('[data-vat-summary-row]')
                .querySelector('[data-summary-nature-value]').value = event.target.value;
        });
        const updateTotal = (row) => {
            const quantity = Number(row.querySelector('[name="quantita[]"]').value) || 0;
            const unitPrice = Number(row.querySelector('[name="prezzo_unitario[]"]').value) || 0;
            const aliquotaIVA = Number(row.querySelector('select[name="aliquota_iva[]"]').value) || 0;
            const imponibile = quantity * unitPrice;
            const iva = imponibile * (aliquotaIVA / 100);
            const totale = imponibile + iva;
            row.querySelector('[name="prezzo_totale[]"]').value = totale.toFixed(2);
        };

        const updateIva = () => {
            const isForfettario = regimeSelect.value === 'regime forfettario';
            beniForm.querySelectorAll('select[name="aliquota_iva[]"]').forEach((ivaSelect) => {
                ivaSelect.querySelectorAll('option').forEach((option) => {
                    option.disabled = isForfettario ? option.value !== '0' : option.value === '0';
                    option.hidden = !isForfettario && option.value === '0';
                });
                ivaSelect.value = isForfettario ? '0' : '22';
                ivaSelect.disabled = isForfettario;
                const hiddenIvaInput = ivaSelect.closest('[data-beni-row]').querySelector('[data-aliquota-iva-value]');
                hiddenIvaInput.value = ivaSelect.value;
                hiddenIvaInput.disabled = !isForfettario;
                updateTotal(ivaSelect.closest('[data-beni-row]'));
            });
            updateSummary();
        };

        const updateBeniVisibility = () => {
            beniForm.hidden = !beniCheckbox.checked;
        };

        beniForm.addEventListener('input', (event) => {
            const row = event.target.closest('[data-beni-row]');
            if (row && ['descrizione[]', 'quantita[]', 'prezzo_unitario[]'].includes(event.target.name)) {
                if (event.target.name !== 'descrizione[]') updateTotal(row);
                updateSummary();
            }
        });
        beniForm.addEventListener('change', (event) => {
            if (event.target.matches('select[name="aliquota_iva[]"]')) {
                const row = event.target.closest('[data-beni-row]');
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
                input.value = input.readOnly ? '0.00' : '';
            });
            newRow.querySelector('[name="aliquota_iva[]"]').value = '0';
            row.after(newRow);
            updateIva();
        });
        regimeSelect.addEventListener('change', updateIva);
        beniCheckbox.addEventListener('change', updateBeniVisibility);
        beniForm.querySelectorAll('[data-beni-row]').forEach(updateTotal);
        updateIva();
        updateSummary();
        updateBeniVisibility();
        document.addEventListener('invoice:new', () => {
            const rows = [...beniForm.querySelectorAll('[data-beni-row]')];
            rows.slice(1).forEach((row) => row.remove());
            if (rows[0]) rows[0].dataset.vatSummaryId = 'item-1';
            nextBeniRowId = 2;
            summaryList.replaceChildren();
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
        if (profileForm && profileForm.dataset.initialized !== 'true') {
            profileForm.dataset.initialized = 'true';
            profileForm.addEventListener('submit', (event) => {
                event.preventDefault();
                document.getElementById('profile-completion').hidden = true;
                clientCompletion.hidden = false;
            });
        }
        return;
    }

    clientCompletion.dataset.initialized = 'true';
    const clientsUrl = '/invoice/process-client.php';
    const clients = new Map();
    const fields = ['id', 'client_type', 'company_name', 'first_name', 'last_name', 'vat_number', 'tax_code', 'address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'crm_status'];
    const modeButton = (mode) => clientCompletion.querySelector(`[data-client-mode="${mode}"]`);

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
        clientForm.elements.tax_code.required = isIndividual;
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
            clientForm.elements.crm_status.value = 'lead';
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

    loadClients();
    document.addEventListener('invoice:new', () => {
        const profileCompletion = document.getElementById('profile-completion');
        const needsProfile = Boolean(profileCompletion && profileForm?.dataset.completed !== 'true');
        if (profileCompletion) profileCompletion.hidden = !needsProfile;
        clientCompletion.hidden = needsProfile;
        billingForm.hidden = true;
        clientSelect.value = '';
        useClientButton.disabled = true;
        modeButton('edit').disabled = true;
        fillClientForm(null);
        setMode('select');
        clientSelect.replaceChildren(new Option('Caricamento clienti...', ''));
        loadClients();
    });

    clientSelect.addEventListener('change', () => {
        const hasClient = clientSelect.value !== '';
        useClientButton.disabled = !hasClient;
        modeButton('edit').disabled = !hasClient;
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

        persistSelectedClient(selectedClientId).finally(() => showBilling());
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
const contentObserver = new MutationObserver(initializeFatturazione);
contentObserver.observe(document.body, { childList: true, subtree: true });
