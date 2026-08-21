# StartUpFlow

StartUpFlow e una piattaforma web per la gestione operativa di startup e PMI. Riunisce in un'unica applicazione i moduli di fatturazione, scadenzario, note spese, burn rate, rilevazione tempi, gestione documentale e CRM.

## Stato attuale

Il progetto e un'applicazione PHP server-side con database MySQL. La navigazione interna usa un router JavaScript leggero (`assets/js/spa-router.js`) che carica i moduli PHP senza richiedere un frontend separato.

## Funzionalita

| Modulo | Percorso |
| --- | --- |
| Dashboard | `/` |
| Fatturazione | `/fatturazione` |
| Scadenzario | `/scadenzario` |
| Nota spese | `/nota-spese` |
| Burn Rate Meter | `/burn-rate-meter` |
| Time Tracker | `/time-tracker` |
| Zero Carta | `/zero-carta` |
| CRM clienti | `/crm-clienti` |
| Chi siamo | `/chi-siamo` |
| Servizi | `/servizi` |
| Contatti | `/contatti` |

Le pagine applicative richiedono una sessione autenticata. Gli utenti possono registrarsi, verificare il proprio indirizzo email, accedere, usare il recupero password, mantenere la sessione tramite Remember Me e modificare l'email dal profilo.

## Struttura

```text
StartUpFlow-by-MandalaTeam/
├── index.php                 # Dashboard protetta
├── fatturazione.php          # Modulo fatturazione
├── scadenzario.php           # Modulo scadenze
├── nota-spese.php            # Modulo note spese
├── burn-rate-meter.php       # Modulo burn rate
├── time-tracker.php          # Rilevazione tempi
├── zero-carta.php            # Gestione documentale
├── crm-clienti.php           # CRM clienti
├── auth/                     # Pagine e handler di autenticazione
├── includes/                 # Database, sessioni, email, audit e layout
│   └── layout/               # Header, footer e modali condivisi
├── assets/                   # CSS, JavaScript, immagini e dati statici
├── database/schema.sql       # Schema MySQL
├── scripts/                  # Utility CLI e manutenzione
├── tests/auth-smoke.sh       # Smoke test HTTP dell'autenticazione
├── uploads/                  # Documenti e scontrini caricati
└── start-dev.sh              # Avvio locale PHP + MailHog
```

## Requisiti

- PHP 8.0 o superiore con estensioni `pdo_mysql`, `mbstring` e `openssl`;
- MySQL 5.7+ o MariaDB compatibile;
- Docker in esecuzione, necessario per MailHog durante lo sviluppo;
- Git.

## Configurazione locale

Dalla directory principale del progetto:

```bash
# Inizializza database e tabelle
mysql -u root -p < database/schema.sql

# Crea la configurazione locale, non versionata
cp includes/config.example.php includes/config.php
```

Modificare `includes/config.php` inserendo le credenziali del database e l'URL dell'applicazione. Non committare password, token o altri segreti.

Per creare o aggiornare un utente attivo di test:

```bash
php scripts/seed-test-user.php
```

Il flusso completo di registrazione, verifica email, login, reset password e cambio email e descritto in [README_LOGIN.md](README_LOGIN.md).

## Avvio locale

Il comando consigliato avvia il server PHP e MailHog insieme:

```bash
bash start-dev.sh
```

L'applicazione sara disponibile su <http://127.0.0.1:8000>. MailHog intercetta le email di sviluppo:

- SMTP: `127.0.0.1:1025`;
- interfaccia web: <http://127.0.0.1:8025>.

Lo script usa l'immagine Docker `mailhog/mailhog` e termina il container quando il server viene arrestato. Per avviare solo PHP:

```bash
php -S 127.0.0.1:8000 -t .
```

In questo caso il servizio email deve essere configurato separatamente. `start-dev.sh` non esegue la pulizia dei token scaduti: usare `scripts/cleanup-expired-tokens.php` come attivita periodica.

## Database

`database/schema.sql` crea il database `login_auth_db` e le tabelle per:

- utenti, stato dell'account e tentativi di login;
- log di audit e rate limiting;
- verifiche email e richieste di cambio email;
- reset password;
- token Remember Me.

La connessione PDO e centralizzata in `includes/db.php`. Le query applicative devono usare statement preparati.

## Sicurezza

Il progetto include:

- sessioni PHP con cookie `HttpOnly` e `SameSite=Lax`;
- rigenerazione della sessione al login;
- token CSRF per i form;
- password memorizzate tramite hashing PHP;
- token email, reset e Remember Me memorizzati come hash;
- validazione client-side e server-side;
- audit log e limiti per account e indirizzo IP sul cambio email;
- sanitizzazione dell'output HTML.

Per l'ambiente di produzione impostare `APP_ENV='prod'` nella configurazione e usare un provider SMTP affidabile.

## Test

Lo smoke test HTTP verifica le pagine e i principali redirect del flusso di autenticazione. Richiede PHP, MySQL, `curl` e una configurazione valida:

```bash
bash tests/auth-smoke.sh
```

Per testare la configurazione del database:

```bash
php tests/test-db.php
```

## Contribuire

Per convenzioni, workflow Git, setup e controlli prima di una Pull Request, consultare [CONTRIBUTING.md](CONTRIBUTING.md).

## Licenza

Il progetto e distribuito con licenza MIT. Consultare [LICENSE](LICENSE).
