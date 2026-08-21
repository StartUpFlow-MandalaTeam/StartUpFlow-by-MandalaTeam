# Contribuire a StartupFlow

Grazie per il tuo interesse nel contribuire a StartupFlow. Questa guida descrive il workflow Git, la configurazione dell'ambiente locale, l'organizzazione del codice e i controlli da eseguire prima di aprire una Pull Request.

## Indice

- [Contribuire a StartupFlow](#contribuire-a-startupflow)
  - [Indice](#indice)
  - [Struttura del team](#struttura-del-team)
  - [Assegnazione dei servizi](#assegnazione-dei-servizi)
  - [Architettura e struttura](#architettura-e-struttura)
    - [Dove intervenire](#dove-intervenire)
  - [Prerequisiti](#prerequisiti)
  - [Setup locale](#setup-locale)
  - [Avvio dell'applicazione](#avvio-dellapplicazione)
  - [Sviluppare una modifica](#sviluppare-una-modifica)
    - [Branch](#branch)
    - [Implementazione](#implementazione)
  - [Test e controlli](#test-e-controlli)
  - [Convenzioni di codice](#convenzioni-di-codice)
  - [Commit e Pull Request](#commit-e-pull-request)
  - [Sicurezza](#sicurezza)
  - [Risoluzione dei conflitti](#risoluzione-dei-conflitti)
  - [Comandi Git utili](#comandi-git-utili)
  - [Riferimenti](#riferimenti)

## Struttura del team

| Membro | Servizi assegnati | Branch di competenza |

|--------|-------------------|----------------------|
| Dev A | ?, ? | `feature/?`, `feature/?` |
| Dev B | ?, ? | `feature/?`, `feature/?` |
| Dev C | ?, ? | `feature/?`, `feature/?` |
| Dev D | ? | `feature/?` |

## Assegnazione dei servizi

| N. | Servizio | File PHP | Branch feature |

|----|----------|----------|----------------|
| 1 | Fatturazione | `fatturazione.php` | `feature/fatturazione` |
| 2 | Scadenzario | `scadenzario.php` | `feature/scadenzario` |
| 3 | Nota Spese | `nota-spese.php` | `feature/nota-spese` |
| 4 | Burn Rate Meter | `burn-rate-meter.php` | `feature/burn-rate-meter` |
| 5 | Time-Tracker | `time-tracker.php` | `feature/time-tracker` |
| 6 | Zero carta | `zero-carta.php` | `feature/zero-carta` |
| 7 | CRM clienti | `crm-clienti.php` | `?` |

Ogni sviluppatore è responsabile di:

- implementare la logica specifica del servizio;
- mantenere coerenti pagina PHP, CSS e JavaScript del modulo;
- integrare il modulo con autenticazione, database e layout condivisi;
- testare il proprio modulo e verificare che non rompa gli altri flussi;
- documentare le modifiche rilevanti.

## Architettura e struttura

StartupFlow è un'applicazione web server-side in PHP, con MySQL come database e JavaScript/CSS vanilla per le interazioni del browser. Non utilizza React, Vite, npm o un client frontend separato.

```text
StartupFlow-by-MandalaTeam/
├── index.php                 # Dashboard protetta
├── fatturazione.php          # Modulo fatturazione
├── scadenzario.php           # Modulo scadenze
├── nota-spese.php            # Modulo note spese
├── burn-rate-meter.php       # Modulo burn rate
├── time-tracker.php          # Modulo rilevazione tempi
├── zero-carta.php            # Modulo gestione documenti
├── crm-clienti.php           # Modulo CRM
├── auth/                     # Pagine e handler autenticazione
├── includes/                 # Connessione DB, sessioni, layout, mail e audit
│   └── layout/               # Header, footer e modali condivisi
├── assets/
│   ├── css/                  # Stili globali e specifici delle pagine
│   ├── js/                   # Navigazione, router, modali e overlay
│   ├── data/                 # Dati statici, ad esempio countries.json
│   └── img/                  # Immagini statiche
├── database/schema.sql       # Schema MySQL
├── scripts/                  # Utility CLI e manutenzione
├── tests/auth-smoke.sh       # Smoke test del flusso autenticazione
├── uploads/                  # Documenti e scontrini caricati dagli utenti
└── start-dev.sh              # Server PHP + MailHog
```

### Dove intervenire

- Una pagina o un modulo: modificare il relativo file PHP e, quando necessario, il CSS in `assets/css/`.
- Layout condiviso: usare `includes/layout/header.php`, `footer.php` o `modals.php`.
- Autenticazione e sessioni: seguire le funzioni esistenti in `includes/auth.php` e gli handler in `auth/`.
- Database: modificare `database/schema.sql` e documentare ogni cambiamento allo schema.
- Interazioni browser: aggiungere o aggiornare gli script in `assets/js/`, mantenendo separata la logica condivisa da quella specifica di una pagina.

Evitare di duplicare header, footer, gestione sessione o connessioni PDO nei file delle pagine. Prima di aggiungere un helper, verificare se esiste già in `includes/`.

## Prerequisiti

- PHP 8.0 o superiore, con estensioni `pdo_mysql`, `mbstring` e `openssl`.
- MySQL 5.7+ o MariaDB compatibile.
- Docker, necessario per avviare MailHog con `start-dev.sh`.
- Git.

Composer e Node.js non sono richiesti dal progetto attuale.

## Setup locale

```bash
git clone <url-del-repository>
cd StartUpFlow-by-MandalaTeam

# Crea il database e le tabelle
mysql -u root -p < database/schema.sql

# Crea la configurazione locale, esclusa dal versionamento
cp includes/config.example.php includes/config.php
```

Modificare `includes/config.php` con le credenziali MySQL e con gli indirizzi locali dell'applicazione e del servizio mail. Non committare mai password, token o altre credenziali.

Per creare un utente attivo di sviluppo:

```bash
php scripts/seed-test-user.php
```

Per i dettagli del flusso di autenticazione e dell'utente di test, consultare `README_LOGIN.md`.

## Avvio dell'applicazione

Dalla root del progetto:

```bash
bash start-dev.sh
```

Lo script avvia:

- applicazione PHP su `http://127.0.0.1:8000`;
- MailHog SMTP sulla porta `1025`;
- interfaccia MailHog su `http://127.0.0.1:8025`.

Lo script richiede Docker in esecuzione. Per avviare solo il server PHP:

```bash
php -S 127.0.0.1:8000 -t .
```

In questo caso il recapito delle email deve essere configurato separatamente. Lo script di sviluppo non esegue la pulizia dei token scaduti; per quella attività usare `scripts/cleanup-expired-tokens.php` secondo le necessità dell'ambiente.

## Sviluppare una modifica

### Branch

Usare un branch dedicato partendo da `main` aggiornato:

```bash
git switch main
git pull origin main
git switch -c feature/<descrizione-breve>
```

Esempi di nomi validi: `feature/fatturazione`, `fix/login-csrf`, `docs/contributing`.

### Implementazione

Prima di modificare una pagina, individuare il suo flusso completo:

1. pagina PHP e form visualizzato;
2. handler PHP che riceve la richiesta;
3. validazione server-side e controllo CSRF;
4. accesso al database tramite PDO;
5. risposta, redirect o messaggio mostrato all'utente;
6. eventuali asset CSS/JS condivisi.

Le validazioni client-side migliorano l'esperienza, ma non sostituiscono mai quelle server-side. Per nuove query usare statement preparati e mantenere la gestione degli errori coerente con `includes/db.php`.

Quando una modifica tocca `database/schema.sql`, includere nella Pull Request le istruzioni di migrazione o le operazioni necessarie per aggiornare un database già esistente.

## Test e controlli

Prima di aprire una Pull Request:

```bash
# Controllo sintattico sui file PHP modificati
php -l percorso/del/file.php

# Smoke test del flusso di autenticazione
bash tests/auth-smoke.sh
```

Lo smoke test richiede una configurazione valida in `includes/config.php`, MySQL raggiungibile e le tabelle già create. Verifica pagine pubbliche, token CSRF, redirect di validazione, reset password e verifica email.

Per le modifiche UI verificare manualmente desktop e mobile, inclusi navigazione, form, messaggi di errore, sessione autenticata e logout. Controllare anche la console del browser e il log del server PHP.

## Convenzioni di codice

- Seguire lo stile già presente nel file modificato e mantenere le modifiche circoscritte.
- Usare `declare(strict_types=1);` nei nuovi file PHP quando compatibile con il contesto.
- Preferire funzioni esistenti in `includes/` a implementazioni duplicate.
- Escapare l'output HTML e validare ogni input lato server.
- Proteggere le richieste POST con CSRF e verificare autorizzazione e sessione prima di operare su dati privati.
- Usare nomi descrittivi per variabili e funzioni.
- Aggiungere commenti solo per chiarire logica non immediata.
- Non includere file di configurazione locali, credenziali o dati personali nei commit.

## Commit e Pull Request

Usare Conventional Commits, con ambito facoltativo riferito al modulo:

```text
feat(fatturazione): aggiunge la creazione di una fattura
fix(auth): corregge la validazione del token di reset
docs: aggiorna la guida di contribuzione
test(auth): estende lo smoke test
refactor(layout): riusa il footer condiviso
chore: aggiorna la configurazione locale
```

Evitare messaggi generici come `fix`, `aggiornamenti` o `WIP`. Preferire commit piccoli e autosufficienti.

La Pull Request deve contenere:

- descrizione del problema e della soluzione;
- file o moduli coinvolti;
- eventuali modifiche al database o alla configurazione;
- test eseguiti e relativo risultato;
- screenshot per cambiamenti visivi significativi.

Checklist consigliata:

- [ ] La modifica è limitata allo scopo della PR.
- [ ] Ho eseguito `php -l` sui file PHP modificati.
- [ ] Ho eseguito `bash tests/auth-smoke.sh` quando la configurazione lo consente.
- [ ] Ho verificato autorizzazione, CSRF e validazione degli input.
- [ ] Ho aggiornato documentazione o schema quando necessario.
- [ ] Non ci sono credenziali o file locali inclusi nella PR.

## Sicurezza

Le modifiche all'autenticazione, alle sessioni, ai token, alle email o agli upload richiedono particolare attenzione. Non registrare password o token nei log, non esporre dettagli che consentano l'enumerazione degli account e mantenere gli upload limitati a percorsi e tipi di file consentiti.

Per vulnerabilità o problemi di sicurezza non aprire una issue pubblica: contattare direttamente i maintainer del progetto seguendo la procedura concordata dal team.

## Risoluzione dei conflitti

```bash
git status
git fetch origin
git merge origin/main
```

Dopo aver risolto manualmente i marker `<<<<<<<`, `=======`, `>>>>>>>`:

```bash
git add <file-risolto>
git commit
php -l <file-php-modificato>
```

Verificare con attenzione i conflitti in `includes/layout/`, `includes/auth.php`, `assets/css/` e `database/schema.sql`, perché possono influenzare più pagine.

## Comandi Git utili

```bash
git status
git diff
git diff --staged
git log --oneline --graph --decorate -20
git switch -c feature/<nome>
git push -u origin feature/<nome>
git fetch origin
git merge origin/main
```

Per annullare modifiche locali non ancora committate, usare `git restore <file>` solo dopo aver verificato che non contengano lavoro da conservare.

## Riferimenti

- [README.md](README.md)
- [README_LOGIN.md](README_LOGIN.md)
- [Schema del database](database/schema.sql)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Documentazione Git](https://git-scm.com/doc)

Grazie per il contributo a StartupFlow.
