// assets/js/spa-router.js
document.addEventListener('DOMContentLoaded', () => {
    const dynamicContent = document.getElementById('dynamic-content');
    const app = document.getElementById('app');

    // Mappa dei percorsi alle pagine
    const routes = {
        // Servizi
        '/fatturazione': { file: '/fatturazione.php', title: 'Fatturazione - StartUpFlow', css: '/assets/css/fatturazione.css' },
        '/scadenzario': { file: '/scadenzario.php', title: 'Scadenzario - StartUpFlow', css: '/assets/css/scadenzario.css' },
        '/nota-spese': { file: '/nota-spese.php', title: 'Nota Spese - StartUpFlow', css: '/assets/css/nota-spese.css' },
        '/burn-rate-meter': { file: '/burn-rate-meter.php', title: 'Burn Rate Meter - StartUpFlow', css: '/assets/css/burn-rate-meter.css' },
        '/time-tracker': { file: '/time-tracker.php', title: 'Time Tracker - StartUpFlow', css: '/assets/css/time-tracker.css' },
        '/zero-carta': { file: '/zero-carta.php', title: 'Zero Carta - StartUpFlow', css: '/assets/css/zero-carta.css' },
        '/crm-clienti': { file: '/crm-clienti.php', title: 'CRM Clienti - StartUpFlow', css: '/assets/css/crm-clienti.css' },

        // Pagine statiche
        '/chi-siamo': { file: '/chi-siamo.php', title: 'Chi Siamo - StartUpFlow', css: '/assets/css/chi-siamo.css' },
        '/servizi': { file: '/servizi.php', title: 'Servizi - StartUpFlow', css: '/assets/css/servizi.css' },
        '/contatti': { file: '/contatti.php', title: 'Contatti - StartUpFlow', css: '/assets/css/contatti.css' },
    };

    // Funzione per mostrare la dashboard
    function showDashboard() {
        removeRouteCss();
        dynamicContent.innerHTML = `
            <div class="card-container">
                <div class="card-row card-row--top">
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-1.webp" alt="Fatturazione">
                        <div class="card-content">
                            <h3 class="card-title">Fatturazione</h3>
                            <p class="card-description">Emetti le tue fatture</p>
                            <a href="/fatturazione" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-2.webp" alt="Scadenzario">
                        <div class="card-content">
                            <h3 class="card-title">Scadenzario</h3>
                            <p class="card-description">Pagamenti & Incassi</p>
                            <a href="/scadenzario" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-3.webp" alt="Nota Spese">
                        <div class="card-content">
                            <h3 class="card-title">Nota Spese</h3>
                            <p class="card-description">Registra le uscite</p>
                            <a href="/nota-spese" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-4.webp" alt="Burn Rate Meter">
                        <div class="card-content">
                            <h3 class="card-title">Burn Rate Meter</h3>
                            <p class="card-description">Il tuo stato di salute</p>
                            <a href="/burn-rate-meter" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                </div>
                <div class="card-row card-row--bottom">
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-5.webp" alt="Time-Tracker">
                        <div class="card-content">
                            <h3 class="card-title">Time-Tracker</h3>
                            <p class="card-description"> Traccia il timing per cliente</p>
                            <a href="/time-tracker" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-6.webp" alt="Zero carta">
                        <div class="card-content">
                            <h3 class="card-title">"Zero carta"</h3>
                            <p class="card-description">Repository documentale</p>
                            <a href="/zero-carta" class="card-button" data-spa="true">Vai!</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-image" src="/assets/img/card-7.webp" alt="CRM clienti">
                        <div class="card-content">
                            <h3 class="card-title">CRM clienti</h3>
                            <p class="card-description">Ciclo di acquisizione clienti</p>
                            <a href="/crm-clienti" class="card-button" data-spa="true">Vai!</a>
                    </div>
                </div>
            </div>
        `;
        document.title = 'Dashboard - StartUpFlow';
        history.pushState({ path: '/' }, '', '/');
    }

    // Rimuove i CSS per-rotta caricati in precedenza
    function removeRouteCss() {
        document.querySelectorAll('link[data-route-css]').forEach(el => el.remove());
    }

    // Carica il CSS della rotta corrente, attendendo che sia pronto
    function loadRouteCss(href) {
        return new Promise((resolve) => {
            if (document.querySelector(`link[data-route-css="${href}"]`)) {
                resolve();
                return;
            }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-route-css', href);
            link.onload = resolve;
            link.onerror = () => resolve(); // non bloccare la pagina se il CSS manca
            document.head.appendChild(link);
        });
    }

    // Funzione per caricare una pagina
    async function loadPage(path) {
        const urlObj = new URL(path, window.location.origin);
        let routePath = urlObj.pathname;
        if (routePath.endsWith('.php')) {
            routePath = routePath.replace('.php', '');
        }
        const route = routes[routePath] || routes[urlObj.pathname] || routes[path];
        if (!route) {
            showDashboard();
            return;
        }

        // Rimuove il CSS della rotta precedente e carica quello della rotta corrente
        removeRouteCss();
        if (route.css) {
            await loadRouteCss(route.css);
        }

        try {
            dynamicContent.innerHTML = '<div class="loading-spinner">Caricamento...</div>';

            const fetchUrl = route.file + urlObj.search;
            const response = await fetch(fetchUrl, {
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    window.location.href = '/auth/login.php';
                    return;
                }
                throw new Error('Pagina non trovata: ' + response.status);
            }

            const html = await response.text();
            dynamicContent.innerHTML = html;
            document.title = route.title;
            history.pushState({ path }, '', path);
        } catch (error) {
            console.error('Errore:', error);
            dynamicContent.innerHTML = `
                <div class="error-container">
                    <h2>Errore</h2>
                    <p>${error.message}</p>
                    <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
                </div>
            `;
        }
    }

    // Gestione dei click sui link SPA
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-spa="true"]');
        if (!link) return;

        e.preventDefault();
        const path = link.getAttribute('href');
        loadPage(path);
    });

    // Gestione delle sottomissioni di form SPA
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        const action = form.getAttribute('action');
        if (!action) return;
        if (action.includes('process-logout') || action.startsWith('/auth/')) return;

        e.preventDefault();

        try {
            dynamicContent.innerHTML = '<div class="loading-spinner">Caricamento...</div>';

            const formData = new FormData(form);
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            let fetchUrl = action;

            const fetchOptions = {
                method: method,
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (method === 'POST') {
                fetchOptions.body = formData;
            } else {
                const params = new URLSearchParams(formData);
                fetchUrl += (fetchUrl.includes('?') ? '&' : '?') + params.toString();
            }

            const response = await fetch(fetchUrl, fetchOptions);
            if (!response.ok) {
                // Il server risponde con un messaggio leggibile (es. 422 di validazione): lo si mostra.
                let message = 'Errore nella richiesta: ' + response.status;
                try {
                    const body = await response.text();
                    const match = body.match(/<p>([\s\S]*?)<\/p>/);
                    if (match) {
                        message = match[1].replace(/<[^>]+>/g, '').trim() || message;
                    }
                } catch (e) {
                    // Corpo non leggibile: resta il messaggio generico.
                }
                throw new Error(message);
            }

            // Azione del form letta dalla query string e confrontata per intero:
            // 'action=store' come prefisso includerebbe 'action=store_order' e 'action=store_contact'.
            const query = fetchUrl.includes('?') ? fetchUrl.split('?').slice(1).join('?') : '';
            const urlParams = new URLSearchParams(query);
            const formAction = urlParams.get('action') || '';

            let targetUrl = '/crm-clienti';
            if (formAction === 'store' || formAction === 'update' || formAction === 'update_status') {
                // Creazione/modifica cliente e cambio stato: si torna alla lista.
                targetUrl = '/crm-clienti';
            } else if (formData.has('client_id')) {
                // Contatto, attivita e ordine: si torna alla scheda del cliente.
                targetUrl = `/crm-clienti?action=show&id=${formData.get('client_id')}`;
            } else if (urlParams.get('id')) {
                targetUrl = `/crm-clienti?action=show&id=${urlParams.get('id')}`;
            }
            loadPage(targetUrl);
        } catch (error) {
            console.error('Errore form:', error);
            dynamicContent.innerHTML = `
                <div class="error-container">
                    <h2>Errore</h2>
                    <p>${error.message}</p>
                    <a href="/" data-spa="true" class="back-link">← Torna alla Dashboard</a>
                </div>
            `;
        }
    });

    // Gestione del pulsante back/forward
    window.addEventListener('popstate', (e) => {
        const path = window.location.pathname + window.location.search;
        const urlObj = new URL(path, window.location.origin);
        if (routes[urlObj.pathname]) {
            loadPage(path);
        } else {
            showDashboard();
        }
    });

    // Carica la pagina iniziale
    const currentPath = window.location.pathname + window.location.search;
    const initialUrlObj = new URL(currentPath, window.location.origin);
    if (window.location.pathname === '/') {
        showDashboard();
    } else if (routes[initialUrlObj.pathname]) {
        loadPage(currentPath);
    } else {
        showDashboard();
    }
});