# StartupFlow

> **Gestione aziendale completa per startup e PMI**

StartupFlow è una piattaforma web per la gestione integrata dei processi aziendali: dall'emissione fatture al monitoraggio finanziario, dalla gestione documentale al CRM clienti.

## 📋 Panoramica del Progetto

StartupFlow è un'applicazione **SPA (Single Page Application)** basata su **React** per il frontend, con backend in **PHP**. L'obiettivo è fornire una soluzione tutto-in-uno per la gestione amministrativa, finanziaria e commerciale delle aziende.

## 🏗️ Struttura del Progetto

```
StartupFlow-by-MandalaTeam/
├── client/                  # SPA React (frontend)
│   ├── src/
│   │   ├── components/     # Componenti condivisi
│   │   │   ├── DashboardLayout.jsx
│   │   │   └── SharedNavbar.jsx
│   │   ├── pages/          # Pagine dei 7 servizi
│   │   │   ├── FatturazionePage.jsx
│   │   │   ├── ScadenzarioPage.jsx
│   │   │   ├── NotaSpesePage.jsx
│   │   │   ├── BurnRateMeterPage.jsx
│   │   │   ├── TimeTrackerPage.jsx
│   │   │   ├── ZeroCartaPage.jsx
│   │   │   └── CrmClientiPage.jsx
│   │   ├── App.jsx         # Router principale
│   │   ├── main.jsx        # Entry point React
│   │   └── index.css       # Stili globali
│   ├── package.json
│   ├── vite.config.js      # Configurazione Vite
│   └── index.html
├── dist/                   # Bundle React buildato (generato)
├── assets/                 # Asset statici (CSS, JS legacy)
│   └── css/
│       ├── navbar.css
│       └── card-container.css
├── auth/                   # Autenticazione PHP
│   ├── register.php
│   └── process-logout.php
├── includes/               # Librerie e configurazioni PHP
│   └── auth.php
├── database/               # Script database
├── uploads/                # File uploadati
├── index.php               # Entry point (wrapper per React)
├── README.md               # Documentazione originale
└── CONTRIBUTING.md         # Guida per sviluppatori
```

## 🎯 I 7 Servizi Principali

| Servizio | Route | Branch | Descrizione |
|----------|-------|--------|-------------|
| **Fatturazione** | `/fatturazione` | `feature/fatturazione` | Emissione e gestione fatture |
| **Scadenzario** | `/scadenzario` | `feature/scadenzario` | Pagamenti e incassi |
| **Nota Spese** | `/nota-spese` | `feature/nota-spese` | Registrazione uscite |
| **Burn Rate Meter** | `/burn-rate-meter` | `feature/burn-rate-meter` | Monitoraggio salute finanziaria |
| **Time-Tracker** | `/time-tracker` | `feature/time-tracker` | Tracciamento tempo lavorato |
| **Zero carta** | `/zero-carta` | `feature/zero-carta` | Repository documentale digitale |
| **CRM clienti** | `/crm-clienti` | `feature/crm-clienti` | Gestione ciclo clienti |

## 🛠️ Tecnologie Utilizzate

### Frontend (SPA)
- **React 18** - Libreria per UI
- **React Router 6** - Gestione routing
- **Vite** - Build tool
- **Material Symbols** - Icone
- **Montserrat** - Font

### Backend
- **PHP 8+** - Linguaggio server-side
- **PDO** - Database abstraction
- **MySQL** - Database

## 📦 Requisiti

### Sviluppo Locale
- Node.js 18+ (per React/Vite)
- PHP 8.0+
- MySQL 8.0+
- Composer (per dipendenze PHP)
- Git

### Produzione
- Server web (Apache/Nginx)
- PHP 8.0+
- MySQL 8.0+
- Node.js (per build React)

## 🚀 Avvio Rapido

### 1. Clonare il repository
```bash
git clone <url-repository>
cd StartupFlow-by-MandalaTeam
```

### 2. Configurare il backend PHP
```bash
# Installare dipendenze PHP
composer install

# Configurare database (vedi database/schema.sql)
# Copiare e configurare includes/config.php
cp includes/config.php.example includes/config.php
# Modificare con le proprie credenziali database
```

### 3. Configurare il frontend React
```bash
cd client
npm install
```

### 4. Avviare lo sviluppo

#### Opzione A: Solo frontend (Vite dev server)
```bash
cd client
npm run dev
# SPA disponibile su http://localhost:3000
```

#### Opzione B: Full stack (PHP + React buildato)
```bash
# Buildare React
cd client
npm run build

# Avviare server PHP (es: con PHP built-in server)
cd ..
php -S localhost:8000
# Applicazione disponibile su http://localhost:8000
```

## 🔧 Script Utili

| Comando | Descrizione |
|---------|-------------|
| `npm run dev` | Avvia Vite dev server (porta 3000) |
| `npm run build` | Builda React per produzione |
| `npm run preview` | Anteprima build di produzione |
| `npm run lint` | Esegue linting del codice |

## 🌐 Routing

L'applicazione utilizza **React Router** per la navigazione client-side:

- `/` - Dashboard principale con card dei servizi
- `/fatturazione` - Modulo fatturazione
- `/scadenzario` - Modulo scadenzario
- `/nota-spese` - Modulo nota spese
- `/burn-rate-meter` - Modulo Burn Rate Meter
- `/time-tracker` - Modulo Time Tracker
- `/zero-carta` - Modulo Zero carta
- `/crm-clienti` - Modulo CRM clienti

## 📁 Gestione File

### Upload documenti
I file uploadati (scontrini, documenti, ecc.) vengono salvati in:
- `/uploads/documenti/` - Documenti generici
- `/uploads/scontrini/` - Scontrini e ricevute

## 🔒 Sicurezza

- Autenticazione basata su sessione PHP
- CSRF protection su tutti i form
- Sanitizzazione input/output

## 📝 Licenza

MIT License - vedi [LICENSE](LICENSE) per dettagli.

---

## 🤝 Contribuire

Vedi [CONTRIBUTING.md](CONTRIBUTING.md) per le line guide su come contribuire al progetto.

## 📧 Contatti

Per domande o supporto, contattare il team di sviluppo.
