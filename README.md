
# StartupFlow - Suite Gestionale per Startup

StartupFlow è una soluzione gestionale progettata per accentrare e automatizzare l'amministrazione aziendale, dalla fatturazione alla gestione finanziaria, riducendo al minimo la burocrazia cartacea.

## Architettura e Moduli

Il software è strutturato con un approccio modulare. Ogni modulo è indipendente per facilitare lo sviluppo parallelo:

1. **Modulo Fatturazione**: Creazione di fatture pro-forma con calcoli automatici.
2. **Automazione Scadenziario**: Monitoraggio dei pagamenti in entrata/uscita.
3. **Gestione Note Spese**: Upload e archiviazione digitale di scontrini e giustificativi.
4. **Burn-Rate Meter**: Analisi della salute finanziaria e proiezione di sopravvivenza.
5. **Time-Tracker**: Cronometro attivo per la rendicontazione delle ore lavorate sui clienti.
6. **Archivio "Zero Carta"**: Repository protetto per contratti e NDA.
7. **CRM Pipeline**: Gestione dei clienti leads con interfaccia in stile Kanban.

## Workflow e Branching Strategy

Per lavorare in modo ordinato, utilizziamo il **Feature Branch Workflow**. Ogni modulo ha il suo branch dedicato.

- `main`: Branch principale. Contiene solo codice stabile e pronto per il deploy.
- `feature/<nome-modulo>`: Branch dove lavoriamo alle nuove funzionalità (es. `feature/fatturazione`, `feature/crm-pipeline`).

### Come contribuire?

1. Effettua il checkout del branch principale: `git checkout main`
2. Crea un nuovo branch per il tuo modulo: `git checkout -b feature/nome-modulo`
3. Lavora sul tuo codice e fai commit frequenti.
4. Quando hai finito, apri una **Pull Request** verso `main` per la revisione del codice.

## Tecnologie Utilizzate

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla).
- **Backend**: PHP (Logica, sessioni, file system).
- **Database**: MySQL (Relazionale).

## Setup Locale

1. Clona il repository: `git clone <url-del-tuo-repo>`
2. Configura il database MySQL usando lo schema definito in `database/schema.sql`.
3. Crea il file `config.php` basandoti su `config.example.php` (NON committare le tue credenziali!).
