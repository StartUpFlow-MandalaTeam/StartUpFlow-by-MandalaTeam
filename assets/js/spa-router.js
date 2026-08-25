// assets/js/spa-router.js
document.addEventListener('DOMContentLoaded', () => {
    const dynamicContent = document.getElementById('dynamic-content');
    const app = document.getElementById('app');

    // Mappa dei percorsi alle pagine
    const routes = {
        // Servizi
        '/fatturazione': { file: '/fatturazione.php', title: 'Fatturazione - StartUpFlow' },
        '/scadenzario': { file: '/scadenzario.php', title: 'Scadenzario - StartUpFlow' },
        '/nota-spese': { file: '/nota-spese.php', title: 'Nota Spese - StartUpFlow' },
        '/burn-rate-meter': { file: '/burn-rate-meter.php', title: 'Burn Rate Meter - StartUpFlow' },
        '/time-tracker': { file: '/time-tracker.php', title: 'Time Tracker - StartUpFlow' },
        '/zero-carta': { file: '/zero-carta.php', title: 'Zero Carta - StartUpFlow' },
        '/crm-clienti': { file: '/crm-clienti.php', title: 'CRM Clienti - StartUpFlow' },

        // Pagine statiche
        '/chi-siamo': { file: '/chi-siamo.php', title: 'Chi Siamo - StartUpFlow' },
        '/servizi': { file: '/servizi.php', title: 'Servizi - StartUpFlow' },
        '/contatti': { file: '/contatti.php', title: 'Contatti - StartUpFlow' },
    };

    // Funzione per mostrare la dashboard
    function showDashboard() {
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
            </div>
        `;
        document.title = 'Dashboard - StartUpFlow';
        history.pushState({ path: '/' }, '', '/');
    }

    // Funzione per caricare una pagina
    async function loadPage(path) {
        const route = routes[path];
        if (!route) {
            showDashboard();
            return;
        }

        try {
            dynamicContent.innerHTML = '<div class="loading-spinner">Caricamento...</div>';

            const response = await fetch(route.file, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
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

    // Gestione del pulsante back/forward
    window.addEventListener('popstate', (e) => {
        const path = window.location.pathname;
        if (routes[path]) {
            loadPage(path);
        } else {
            showDashboard();
        }
    });

    // Carica la pagina iniziale
    if (window.location.pathname === '/') {
        showDashboard();
    } else if (routes[window.location.pathname]) {
        loadPage(window.location.pathname);
    } else {
        showDashboard();
    }
});
