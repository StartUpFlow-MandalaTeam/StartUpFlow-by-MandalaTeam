# Feature: Burn Rate Meter

## Descrizione

Modulo per monitorare lo stato di salute finanziaria dell'azienda.

## Compiti

- [ ] Implementare dashboard finanziaria
- [ ] Calcolo burn rate (tasso di consumo risorse)
- [ ] Proiezione runway (quanti mesi di liquidità rimangono)
- [ ] Visualizzazione grafici (linee, barre)

## Route

- `/burn-rate-meter` - Pagina principale

## Dipendenze

- React Router (già incluso in main)
- Libreria grafici (es: chart.js, recharts)

## Note

- Lavorare solo su file in `client/src/pages/BurnRateMeterPage.jsx` e componenti correlati
- Non modificare file condivisi (navbar, layout, router)
- Fare merge da main prima di iniziare: `git merge main`

# Feature: Time-Tracker

## Descrizione
Modulo per tracciare il tempo di lavoro fatturabile per cliente.

## Compiti
- [ ] Implementare timer avvio/stop
- [ ] Assegnazione tempo a clienti/progetti
- [ ] Report ore lavorate
- [ ] Integrazione con modulo fatturazione

## Route
- `/time-tracker` - Pagina principale

## Dipendenze
- React Router (già incluso in main)
- Libreria per gestione tempo (es: date-fns)

## Note
- Lavorare solo su file in `client/src/pages/TimeTrackerPage.jsx` e componenti correlati
- Non modificare file condivisi (navbar, layout, router)
- Fare merge da main prima di iniziare: `git merge main`

# Feature: Zero carta

## Descrizione
Repository documentale digitale per eliminare la carta.

## Compiti
- [ ] Implementare upload e categorizzazione documenti
- [ ] Motore di ricerca documenti
- [ ] Anteprima documenti (PDF, immagini)
- [ ] Gestione permessi accesso
- [ ] Versioning documenti

## Route
- `/zero-carta` - Pagina principale

## Dipendenze
- React Router (già incluso in main)
- Libreria per visualizzazione PDF (es: react-pdf)

## Note
- Lavorare solo su file in `client/src/pages/ZeroCartaPage.jsx` e componenti correlati
- Non modificare file condivisi (navbar, layout, router)
- Fare merge da main prima di iniziare: `git merge main`
