# Academy aziendale

Piattaforma interna per la gestione dei percorsi formativi dei dipendenti.

Il progetto è organizzato come monorepo con separazione netta tra:

- `frontend/`: applicazione Angular CSR;
- `backend/`: API REST PHP Slim;
- MariaDB/MySQL: database relazionale.

## Deployment pubblico

- Frontend Angular: <https://esame-rumen-bortoletto.vercel.app>
- Backend API: <https://rumen.alwaysdata.net/api>
- Verifica servizi: <https://rumen.alwaysdata.net/api/health>
- Collezione Postman: `backend/docs/academy-api.postman_collection.json`
- Ambiente Postman locale: `backend/docs/academy-local.postman_environment.json`

Tutte le API applicative, incluso l'endpoint di verifica, richiedono autenticazione
tranne il login. Le credenziali degli utenti di test sono riportate di seguito.

## Requisiti

- PHP 8.2 o successivo;
- Composer;
- Node.js e npm;
- MariaDB 10.4+ oppure MySQL 8+;
- XAMPP può essere utilizzato per MariaDB e phpMyAdmin.

## Avvio locale

### 1. Database

1. Avviare Apache e MySQL dal pannello XAMPP.
2. Aprire `http://localhost/phpmyadmin`.
3. Creare un database chiamato `esame_its` con codifica `utf8mb4`.
4. Selezionare il database e importare `backend/database/schema.sql`.

Lo script è rieseguibile: elimina e ricrea le tre tabelle applicative.

### 2. Backend

Da terminale, nella cartella principale:

```powershell
cd backend
composer install
Copy-Item .env.example .env
```

Configurare `backend/.env`:

```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=esame_its
DB_USER=root
DB_PASSWORD=
JWT_SECRET=inserire-una-chiave-casuale-di-almeno-32-caratteri
```

Avviare Slim:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

Base URL locale delle API:

```text
http://127.0.0.1:8080/api
```

### 3. Frontend

In un secondo terminale:

```powershell
cd frontend
npm install
npm start
```

Aprire `http://localhost:4200`.

## Credenziali iniziali

### Referente Academy

```text
Email: academy@azienda.it
Password: Academy2026!
Ruolo: REFERENTE_ACADEMY
```

### Dipendenti

Password comune: `Dipendente2026!`

```text
marco.dipendente@azienda.it
giulia.dipendente@azienda.it
luca.dipendente@azienda.it
sara.dipendente@azienda.it
```

Le password sono memorizzate esclusivamente come hash. La registrazione usa
`password_hash()` e il login usa `password_verify()`.

## Autenticazione

Il login restituisce un token JWT valido per 8 ore. Le richieste protette devono
inviare l'header:

```http
Authorization: Bearer TOKEN_JWT
```

Il middleware controlla firma e scadenza del token, esistenza dell'utente, stato
attivo e ruolo corrente letto dal database.

Il logout JWT è stateless: il client elimina il token memorizzato.

## API

| Metodo | Percorso | Autorizzazione | Descrizione |
|---|---|---|---|
| POST | `/api/utenti/login` | Pubblica | Login e rilascio JWT |
| POST | `/api/utenti/register` | Referente | Creazione utente |
| GET | `/api/utenti/me` | Autenticato | Profilo corrente |
| POST | `/api/utenti/logout` | Autenticato | Logout |
| GET | `/api/utenti/dipendenti` | Referente | Elenco dipendenti attivi |
| GET | `/api/corsi` | Referente | Catalogo con filtri |
| GET | `/api/corsi/{id}` | Referente | Dettaglio corso |
| POST | `/api/corsi` | Referente | Creazione corso |
| PUT | `/api/corsi/{id}` | Referente | Modifica corso |
| PUT | `/api/corsi/{id}/disattiva` | Referente | Disattivazione corso |
| DELETE | `/api/corsi/{id}` | Referente | Eliminazione se non assegnato |
| GET | `/api/assegnazioni-corsi` | Autenticato | Elenco autorizzato e filtrato |
| GET | `/api/assegnazioni-corsi/{id}` | Autenticato | Dettaglio autorizzato |
| POST | `/api/assegnazioni-corsi` | Referente | Nuova assegnazione |
| PUT | `/api/assegnazioni-corsi/{id}` | Referente | Modifica assegnazione |
| PUT | `/api/assegnazioni-corsi/{id}/annulla` | Referente | Annullamento |
| DELETE | `/api/assegnazioni-corsi/{id}` | Referente | Annullamento equivalente |
| PUT | `/api/assegnazioni-corsi/{id}/completa` | Dipendente proprietario | Completamento |
| GET | `/api/statistiche/academy` | Referente | Statistiche aggregate |

Il dipendente non può scegliere un altro `dipendente_id`: il backend forza sempre
l'identificativo contenuto nel token e blocca il dettaglio di assegnazioni altrui.

### Filtri corsi

```text
GET /api/corsi?categoria=Sicurezza&attivo=true
```

- `categoria`;
- `attivo`: `true` oppure `false`.

### Filtri assegnazioni

```text
GET /api/assegnazioni-corsi?stato=ASSEGNATO&categoria=Sicurezza
```

- `stato`: `ASSEGNATO`, `COMPLETATO`, `SCADUTO`, `ANNULLATO`;
- `categoria`;
- `corso_id`;
- `dipendente_id`, utilizzabile dal referente;
- `scadenza_da` e `scadenza_a` in formato `YYYY-MM-DD`.

Le assegnazioni oltre la scadenza ancora in stato `ASSEGNATO` vengono aggiornate
automaticamente a `SCADUTO`.

### Filtri statistiche

```text
GET /api/statistiche/academy?mese=2026-05&categoria=Sicurezza
```

- `mese` nel formato `YYYY-MM`;
- oppure `data_inizio` e `data_fine` nel formato `YYYY-MM-DD`;
- `categoria`;
- `dipendente_id`.

La risposta contiene, per mese e categoria:

- numero delle assegnazioni;
- numero dei completamenti;
- percentuale di completamento.

## Codici HTTP principali

| Codice | Significato |
|---|---|
| 200 | Operazione riuscita |
| 201 | Risorsa creata |
| 401 | Token mancante/non valido oppure credenziali errate |
| 403 | Ruolo o proprietà del dato non sufficienti |
| 404 | Risorsa non trovata |
| 409 | Conflitto, ad esempio email duplicata o corso collegato |
| 422 | Validazione server non superata |
| 500 | Errore interno gestito in formato JSON |

## Test con Postman

File disponibili:

- `backend/docs/academy-api.postman_collection.json`;
- `backend/docs/academy-local.postman_environment.json`.

Procedura:

1. Aprire Postman e scegliere **Import**.
2. Importare entrambi i file.
3. Selezionare l'ambiente **Academy - locale**.
4. Eseguire **Login referente Academy**: il test salva automaticamente il JWT.
5. Eseguire le richieste per utenti, corsi, assegnazioni e statistiche.
6. Per provare le operazioni del dipendente, eseguire **Login dipendente Marco**.
7. Per tornare alle funzioni gestionali, ripetere il login del referente.

La collezione contiene anche richieste che producono intenzionalmente errori
`401` e `422`, utili per dimostrare validazioni e sicurezza.

## Dati iniziali

Lo script SQL fornisce:

- 1 referente Academy;
- 4 dipendenti;
- 7 corsi in categorie differenti, incluso un corso inattivo;
- 20 assegnazioni distribuite su più mesi;
- tutti gli stati applicativi richiesti;
- dati sufficienti per filtri, autorizzazioni e statistiche.
