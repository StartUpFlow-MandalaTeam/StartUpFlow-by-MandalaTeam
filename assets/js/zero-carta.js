document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-zero-carta-tab]');
    if (!tab || !tab.closest('.zero-carta')) return;

    const page = tab.closest('.zero-carta');
    const selectedTab = tab.dataset.zeroCartaTab;
    const cards = page.querySelector('[data-zero-carta-cards]');
    cards.hidden = true;

    page.querySelectorAll('[data-zero-carta-tab]').forEach((item) => {
        const isSelected = item === tab;
        item.classList.toggle('is-active', isSelected);
        item.setAttribute('aria-selected', String(isSelected));
    });

    page.querySelectorAll('[data-zero-carta-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.zeroCartaPanel !== selectedTab;
    });
});

document.addEventListener('click', (event) => {
    const backButton = event.target.closest('[data-zero-carta-back]');
    if (!backButton) return;

    const page = backButton.closest('.zero-carta');
    page.querySelector('[data-zero-carta-cards]').hidden = false;
    page.querySelectorAll('[data-zero-carta-tab]').forEach((tab) => {
        tab.classList.remove('is-active');
        tab.setAttribute('aria-selected', 'false');
    });
    page.querySelectorAll('[data-zero-carta-panel]').forEach((panel) => {
        panel.hidden = true;
    });
    page.querySelector('[data-zero-carta-tab]').focus();
});

function filterZeroCartaDocuments(page) {
    const nameFilter = page.querySelector('[data-zero-carta-filter="name"]').value.trim().toLocaleLowerCase();
    const clientFilter = page.querySelector('[data-zero-carta-filter="client"]').value;
    const typeFilter = page.querySelector('[data-zero-carta-filter="type"]').value;
    const documents = page.querySelectorAll('[data-zero-carta-document]');
    let visibleCount = 0;

    documents.forEach((document) => {
        const nameMatches = document.dataset.documentName.toLocaleLowerCase().includes(nameFilter);
        const clientMatches = !clientFilter || document.dataset.documentClient === clientFilter;
        const typeMatches = !typeFilter || document.dataset.documentType === typeFilter;
        const isVisible = nameMatches && clientMatches && typeMatches;

        document.hidden = !isVisible;
        if (isVisible) visibleCount += 1;
    });

    page.querySelector('[data-zero-carta-no-matches]').hidden =
        documents.length === 0 || visibleCount > 0;
}

document.addEventListener('input', (event) => {
    const filter = event.target.closest('[data-zero-carta-filter]');
    if (filter && filter.closest('.zero-carta')) {
        filterZeroCartaDocuments(filter.closest('.zero-carta'));
    }
});

document.addEventListener('change', (event) => {
    const filter = event.target.closest('[data-zero-carta-filter]');
    if (filter && filter.closest('.zero-carta')) {
        filterZeroCartaDocuments(filter.closest('.zero-carta'));
    }
});

function updateZeroCartaFileName(input) {
    const dropzone = input.closest('.zero-carta__dropzone');
    const fileName = dropzone.querySelector('.zero-carta__file-name');
    fileName.textContent = input.files.length
        ? input.files[0].name
        : 'Solo PDF o DOCX (massimo 10 MB)';
}

document.addEventListener('change', (event) => {
    if (event.target.matches('.zero-carta__file')) {
        updateZeroCartaFileName(event.target);
    }
});

document.addEventListener('dragover', (event) => {
    const dropzone = event.target.closest('.zero-carta__dropzone');
    if (!dropzone) return;

    event.preventDefault();
    dropzone.classList.add('is-dragging');
});

document.addEventListener('dragleave', (event) => {
    const dropzone = event.target.closest('.zero-carta__dropzone');
    if (dropzone && !dropzone.contains(event.relatedTarget)) {
        dropzone.classList.remove('is-dragging');
    }
});

document.addEventListener('drop', (event) => {
    const dropzone = event.target.closest('.zero-carta__dropzone');
    if (!dropzone) return;

    event.preventDefault();
    dropzone.classList.remove('is-dragging');
    const input = dropzone.querySelector('.zero-carta__file');
    if (event.dataTransfer.files.length) {
        input.files = event.dataTransfer.files;
        updateZeroCartaFileName(input);
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.zero-carta__form');
    if (!form) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    if (form.dataset.uploading === 'true') return;

    form.dataset.uploading = 'true';
    const status = form.querySelector('.zero-carta__status');
    const submit = form.querySelector('.zero-carta__submit');
    submit.disabled = true;
    status.textContent = 'Caricamento in corso...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Impossibile caricare il documento.');
        }

        window.location.reload();
    } catch (error) {
        status.textContent = error.message || 'Errore durante il caricamento.';
        submit.disabled = false;
        delete form.dataset.uploading;
    }
});
