const initializeNotaSpese = () => {
    const root = document.querySelector('.ns-actions-menu');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const container = root.closest('.ns');
    const browse = container.querySelector('#ns-browse');
    const create = container.querySelector('#ns-create');
    const form = container.querySelector('#form-nota-spese');

    // Mostra una sola vista alla volta: 'home' | 'browse' | 'create'
    const showView = (view) => {
        root.hidden = view !== 'home';
        browse.hidden = view !== 'browse';
        create.hidden = view !== 'create';
    };

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ns-action]');
        if (!button) return;
        showView(button.dataset.nsAction === 'browse' ? 'browse' : 'create');
    });

    container.querySelectorAll('[data-ns-action="home"]').forEach((btn) => {
        btn.addEventListener('click', () => showView('home'));
    });

    // Invio AJAX: il PHP risponde con il frammento aggiornato, che sostituisce quello vecchio
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Richiesta fallita (' + response.status + ')');

            const html = await response.text();
            const nuovo = new DOMParser().parseFromString(html, 'text/html').querySelector('.ns');
            if (nuovo) container.replaceWith(nuovo);
        } catch (err) {
            alert('Errore: ' + err.message);
            btn.disabled = false;
        }
    });

    showView('home');
};

document.addEventListener('DOMContentLoaded', initializeNotaSpese);
const notaSpeseObserver = new MutationObserver(initializeNotaSpese);
notaSpeseObserver.observe(document.body, { childList: true, subtree: true });