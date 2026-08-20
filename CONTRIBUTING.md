# Contribuire a StartupFlow

Grazie per voler contribuire a StartupFlow! Questo documento fornisce le line guide per lavorare al progetto in team, gestire i branch Git e collaborare efficacemente.

## 📋 Indice

1. [Struttura del Team](#-struttura-del-team)
2. [Assegnazione dei Servizi](#-assegnazione-dei-servizi)
3. [Workflow Git](#-workflow-git)
4. [Setup Iniziale](#-setup-iniziale)
5. [Sviluppo di una Feature](#-sviluppo-di-una-feature)
6. [Convenzioni di Commit](#-convenzioni-di-commit)
7. [Pull Request](#-pull-request)
8. [Review del Codice](#-review-del-codice)
9. [Merge e Deploy](#-merge-e-deploy)
10. [Risoluzione Conflitti](#-risoluzione-conflitti)
11. [Comandi Git Utili](#-comandi-git-utili)

---

## 👥 Struttura del Team

| Membro | Servizi Assegnati | Branch di Competenza |
|--------|-------------------|---------------------|
| Dev A | Fatturazione, Scadenzario | `feature/fatturazione`, `feature/scadenzario` |
| Dev B | Nota Spese, Burn Rate Meter | `feature/nota-spese`, `feature/burn-rate-meter` |
| Dev C | Time-Tracker, Zero carta | `feature/time-tracker`, `feature/zero-carta` |
| Team Lead (Tu) | CRM clienti | `feature/crm-clienti` |

> **Nota**: Chi ha implementato il sistema di login lavora su un solo servizio (CRM clienti).

---

## 🎯 Assegnazione dei Servizi

I 7 servizi del progetto sono rappresentati dalle card nella dashboard:

| N° | Servizio | Route SPA | Branch Feature | Colore Card |
|---|----------|-----------|----------------|-------------|
| 1 | Fatturazione | `/fatturazione` | `feature/fatturazione` | - |
| 2 | Scadenzario | `/scadenzario` | `feature/scadenzario` | - |
| 3 | Nota Spese | `/nota-spese` | `feature/nota-spese` | - |
| 4 | Burn Rate Meter | `/burn-rate-meter` | `feature/burn-rate-meter` | - |
| 5 | Time-Tracker | `/time-tracker` | `feature/time-tracker` | - |
| 6 | Zero carta | `/zero-carta` | `feature/zero-carta` | - |
| 7 | CRM clienti | `/crm-clienti` | `feature/crm-clienti` | - |

**Ogni sviluppatore è responsabile di:**
- Implementare la logica specifica del servizio
- Creare i componenti React necessari
- Integrare con il backend PHP (API)
- Testare il proprio modulo
- Documentare il codice

---

## 🔄 Workflow Git

Utilizziamo il **Feature Branch Workflow** con le seguenti regole:

```
main (branch principale)
├── feature/fatturazione (Dev A)
├── feature/scadenzario (Dev A)
├── feature/nota-spese (Dev B)
├── feature/burn-rate-meter (Dev B)
├── feature/time-tracker (Dev C)
├── feature/zero-carta (Dev C)
└── feature/crm-clienti (Team Lead)
```

### Regole Fondamentali

1. **`main` è sempre stabile** - Contiene solo codice funzionante e testato
2. **Non lavorare mai direttamente su `main`** - Ogni modifica passa per un branch feature
3. **Ogni feature ha il suo branch** - Non mischiare il lavoro su più servizi in un solo branch
4. **Merge frequenti da `main`** - prima di iniziare a lavorare, assicurati che il tuo branch sia aggiornato

---

## 🚀 Setup Iniziale

### 1. Clonare il repository

```bash
# Clonare il repository
cd /percorso/dove/lavorare
git clone <url-del-repository>
cd StartupFlow-by-MandalaTeam
```

### 2. Configurare l'ambiente locale

```bash
# Installare dipendenze PHP (se non già fatto)
composer install

# Configurare il database
# 1. Creare database MySQL
# 2. Importare schema: mysql -u username -p database_name < database/schema.sql
# 3. Configurare includes/config.php con le tue credenziali
cp includes/config.php.example includes/config.php
nano includes/config.php  # Modificare con i tuoi dati

# Installare dipendenze Node.js per React
cd client
npm install
cd ..
```

### 3. Avviare lo sviluppo

**Opzione A - Sviluppo frontend isolato (consigliato per test React):**
```bash
cd client
npm run dev
# Applicazione disponibile su http://localhost:3000
```

**Opzione B - Sviluppo full stack:**
```bash
# In un terminale: avviare server PHP
php -S localhost:8000

# In un altro terminale: avviare Vite in modalità watch
cd client
npm run dev -- --port 3000

# PHP su http://localhost:8000, React su http://localhost:3000
```

**Opzione C - Full stack con React buildato:**
```bash
cd client
npm run build
cd ..
php -S localhost:8000
# Applicazione completa su http://localhost:8000
```

---

## 💻 Sviluppo di una Feature

### Passo 1: Selezionare il branch corretto

```bash
# Verificare di essere su main
git checkout main

# Aggiornare main con l'origin
git pull origin main

# Passare al proprio branch feature
git checkout feature/<nome-servizio>

# Example per Dev A che lavora su Fatturazione:
git checkout feature/fatturazione
```

### Passo 2: Aggiornare il branch con main

**Prima di iniziare a lavorare**, assicurati che il tuo branch abbia le ultime modifiche da main:

```bash
# Dal tuo branch feature:
git merge main

# Se ci sono conflitti, risolverli (vedi sezione Risoluzione Conflitti)
# Poi fare commit del merge:
git commit -m "merge: Aggiornamento da main"
```

### Passo 3: Implementare la feature

**Dove lavorare:**
- Il tuo codice va **solo** nella cartella del servizio assegnato
- Esempio per Fatturazione: `client/src/pages/FatturazionePage.jsx`

**Cosa modificare:**
- ✅ Componenti specifici del servizio
- ✅ Logica business del modulo
- ✅ API/endpoint specifici
- ✅ Stili CSS specifici del modulo

**Cosa NON modificare:**
- ❌ `client/src/App.jsx` (router)
- ❌ `client/src/components/SharedNavbar.jsx`
- ❌ `client/src/components/DashboardLayout.jsx`
- ❌ `client/src/main.jsx`
- ❌ `index.php` (a meno di accordi con il team lead)
- ❌ File in `assets/css/` condivisi

### Passo 4: Testare localmente

```bash
# Testare il proprio modulo
cd client
npm run dev

# Verificare che:
# - La route funziona (es: /fatturazione)
# - La navbar e il layout siano integrati correttamente
# - Non ci siano errori nella console
# - Il modulo non rompa le altre funzionalità
```

### Passo 5: Commit frequenti

Fare commit **frequenti e atomic** (uno per ogni cambiamento logico):

```bash
# Verificare lo stato
git status

# Aggiungere i file modificati
git add client/src/pages/FatturazionePage.jsx
# Oppure aggiungere tutti i file modificati:
git add -A

# Fare commit con messaggio descrittivo
git commit -m "feat(fatturazione): Aggiunto form creazione fattura"

# Spingere sul branch remoto
git push origin feature/fatturazione
```

---

## ✍️ Convenzioni di Commit

Utilizziamo la convenzione **Conventional Commits** per i messaggi:

```
tipo(ambito): descrizione

[corpo opzionale]

[footer opzionale]
```

### Tipi di commit

| Tipo | Quando usare | Esempio |
|------|--------------|---------|
| `feat` | Nuova feature | `feat(fatturazione): Aggiunto modulo fatturazione` |
| `fix` | Bug fix | `fix(scadenzario): Corretto calcolo scadenze` |
| `docs` | Documentazione | `docs: Aggiornato README per il modulo` |
| `style` | Cambiamenti che non influenzano il codice | `style: Sistemati margini card` |
| `refactor` | Rifattorizzazione codice | `refactor: Estratti componenti comuni` |
| `perf` | Ottimizzazione prestazioni | `perf: Ottimizzata query database` |
| `test` | Aggiunta/modifica test | `test: Aggiunti test per nota spese` |
| `chore` | Manutenzione | `chore: Aggiornate dipendenze npm` |
| `merge` | Merge da altri branch | `merge: Aggiornamento da main` |

### Ambito

L'ambito è il nome del modulo o della parte del codice interessata:
- `fatturazione`
- `scadenzario`
- `nota-spese`
- `burn-rate-meter`
- `time-tracker`
- `zero-carta`
- `crm-clienti`
- `navbar`
- `layout`
- `router`
- `auth`

### Esempi

```bash
# Buono
git commit -m "feat(fatturazione): Aggiunto modulo emissione fatture"
git commit -m "fix(scadenzario): Corretto bug visualizzazione scadenze"
git commit -m "docs(nota-spese): Aggiornata documentazione modulo"

# Cattivo (da evitare)
git commit -m "fix"
git commit -m "aggiornamenti"
git commit -m "WIP"
```

---

## 📤 Pull Request

### Quando aprire una PR

- La feature è **completa** e funzionante
- Il codice è **testato**
- Tutti i **conflitti** sono risolti
- Il branch è **aggiornato** con main

### Come aprire una PR

1. **Spingere il branch su GitHub**
   ```bash
   git push origin feature/<nome-servizio>
   ```

2. **Andare su GitHub** e creare una nuova Pull Request:
   - Base branch: `main`
   - Compare branch: `feature/<nome-servizio>`
   - Titolo: `[feat] <nome-servizio>: <descrizione breve>`
   - Descrizione: Spiegare cosa è stato fatto, eventuali note per i reviewer

3. **Aggiungere i reviewer** (assegnare agli altri membri del team)

4. **Collegare alla issue** (se esiste)

### Template PR

```markdown
## Descrizione

[Breve descrizione della feature implementata]

## Cambiamenti

- [x] Implementata funcionalità X
- [x] Aggiunto componente Y
- [x] Testato su desktop e mobile
- [ ] ...

## Note

[Eventuali note per i reviewer, decisioni di design, ecc.]

## Screenshot

[Se utile, allegare screenshot o GIF]

## Checklist

- [x] Codice testato localmente
- [x] Branch aggiornato con main
- [x] Nessun conflitto
- [x] Convenzioni di commit rispettate
```

---

## 👀 Review del Codice

### Per il Reviewer

**Cosa controllare:**

1. **Funzionalità**
   - La feature funziona come descritto?
   - Ci sono errori o comportamenti inattesi?

2. **Codice**
   - È pulito e leggibile?
   - Segue le convenzioni del progetto?
   - Ci sono code smell o anti-pattern?

3. **Integrazione**
   - Il modulo si integra correttamente con il resto?
   - Vengono rispettate le route?
   - Non ci sono conflitti con altri moduli?

4. **Sicurezza**
   - I dati utente sono gestiti in modo sicuro?
   - Ci sono vulnerabilità evidenti?

### Commenti alla PR

- Sii **costruttivo** e **rispettoso**
- Spiega **perché** una cosa va cambiata
- Proponi **soluzioni** concrete

Esempi:
```
❌ "Questo codice è brutto"

✅ "Questa funzione è un po' lunga. Che ne dici di estrarre la logica X in una funzione separata? Così sarebbe più leggibile e riutilizzabile."
```

### Rispondere ai commenti

- Ringrazia per il feedback
- Rispondi a ogni commento
- Se non sei d'accordo, spiega il perché
- Fai le modifiche richieste con commit separati

---

## 🔄 Merge e Deploy

### Merge in main

Solo il **Team Lead** o una persona autorizzata può fare merge in `main`.

**Procedura:**

1. **Verificare la PR**
   - Tutti i reviewer hanno approvato
   - Tutti i commenti sono stati risolti
   - I test passano

2. **Fare merge**
   ```bash
   git checkout main
   git pull origin main
   git merge feature/<nome-servizio>
   ```

3. **Risolvere eventuali conflitti** (vedi prossima sezione)

4. **Spingere su main**
   ```bash
   git push origin main
   ```

5. **Chiudere la PR** su GitHub

### After Merge

- Il branch feature può essere **cancellato** (opzionale)
- Il developper può **creare un nuovo branch** per la prossima feature

---

## ⚠️ Risoluzione Conflitti

### Quando si verificano conflitti

I conflitti si verificano quando:
- Due persone hanno modificato lo stesso file
- Il branch non è aggiornato con main
- Ci sono cambiamenti incompatibili

### Come risolvere

1. **Identificare i conflitti**
   ```bash
   git status
   # Git mostrerà: "Unmerged paths"
   ```

2. **Aprire i file in conflitto**
   Git segnalerà i conflitti con:
   ```
   <<<<<<< HEAD
   Le tue modifiche
   =======
   Le modifiche dall'altro branch
   >>>>>>> branch-name
   ```

3. **Modificare manualmente** il file per risolvere il conflitto

4. **Segnare come risolto**
   ```bash
   git add <file-conflitto>
   ```

5. **Completare il merge**
   ```bash
   git commit -m "merge: Risolto conflitto per <file>"
   ```

### Esempio pratico

```bash
# Stai su feature/fatturazione
git merge main

# Se ci sono conflitti:
# 1. Apri i file in conflitto (es: client/src/App.jsx)
# 2. Trova le sezioni segnate con <<<<<<< >>>>>>>
# 3. Modifica il file mantenendo la versione corretta
# 4. Salva il file

# 5. Segnala risolto
git add client/src/App.jsx

# 6. Completa il merge
git commit
```

### Tool utili per i conflitti

- **VS Code**: Ha un ottimo supporto per risolvere i conflitti
- **`git diff`**: Mostra le differenze
- **`git log --graph --oneline`**: Visualizza la storia dei branch
- **`git mergetool`**: Strumento interattivo per risolvere conflitti

---

## 💡 Comandi Git Utili

### Comandi Base

| Comando | Descrizione |
|---------|-------------|
| `git status` | Mostra lo stato del repository |
| `git log` | Mostra la storia dei commit |
| `git log --oneline` | Storia in formato compatto |
| `git log --graph --oneline` | Storia con grafico dei branch |
| `git diff` | Mostra le differenze non committate |
| `git diff --staged` | Mostra le differenze nel staging |

### Lavorare con i Branch

| Comando | Descrizione |
|---------|-------------|
| `git branch` | Lista i branch locali |
| `git branch -a` | Lista tutti i branch (locali e remoti) |
| `git branch <nome>` | Crea un nuovo branch |
| `git branch -d <nome>` | Elimina un branch locale |
| `git checkout <nome>` | Passa a un branch |
| `git checkout -b <nome>` | Crea e passa a un nuovo branch |
| `git push origin <nome>` | Spinge un branch su remote |
| `git push origin --delete <nome>` | Elimina un branch remoto |

### Sincronizzare con Remote

| Comando | Descrizione |
|---------|-------------|
| `git fetch` | Scarica i cambiamenti dal remote |
| `git pull` | Scarica e mergea i cambiamenti |
| `git pull origin main` | Aggiorna il branch corrente con main |
| `git push` | Spinge i cambiamenti localmente committati |

### Merge e Rebase

| Comando | Descrizione |
|---------|-------------|
| `git merge <branch>` | Mergea un branch nel corrente |
| `git merge main` | Aggiorna con le modifiche da main |
| `git rebase <branch>` | Riapplica i tuoi commit sopra un altro branch |
| `git rebase main` | Riapplica i tuoi commit sopra main (alternativa a merge) |

### Stash (Salvataggio temporaneo)

| Comando | Descrizione |
|---------|-------------|
| `git stash` | Salva le modifiche non committate |
| `git stash list` | Lista tutti gli stash |
| `git stash pop` | Ripristina l'ultimo stash |
| `git stash apply` | Applica uno stash senza rimuoverlo |
| `git stash drop` | Elimina uno stash |

### Reset

| Comando | Descrizione | **Attenzione** |
|---------|-------------|----------------|
| `git reset --soft HEAD~1` | Annulla l'ultimo commit, tengono le modifiche | Non distruttivo |
| `git reset --mixed HEAD~1` | Annulla commit e unstage | Non distruttivo |
| `git reset --hard HEAD~1` | Annulla TUTTO (commit e modifiche) | **DISTRUTTIVO** |
| `git reset --hard origin/main` | Resetta a main remoto | **DISTRUTTIVO** |

> ⚠️ **Non usare `--hard` a meno che tu non sia sicuro di quello che stai facendo!**

---

## 📚 Riferimenti

- [Conventional Commits](https://www.conventionalcommits.org/)
- [Git Documentation](https://git-scm.com/doc)
- [GitHub Flow](https://docs.github.com/en/get-started/quickstart/github-flow)
- [React Documentation](https://react.dev/)
- [Vite Documentation](https://vitejs.dev/)

---

## 🆘 Help

Se hai dubbi o incontri problemi:

1. **Leggi questa guida** - La risposta c'è probabilmente qui
2. **Chiedi al team** - Usa il canale di comunicazione del team (Slack, Telegram, ecc.)
3. **Cerca su Google** - "git <problema>" spesso risolve
4. **Stack Overflow** - C'è già una risposta per quasi tutto

---

**Buon lavoro!** 🚀
