# Academy aziendale

Applicazione per gestire i corsi di formazione aziendali, realizzata con:

- frontend Angular;
- backend PHP Slim con API REST;
- database MariaDB/MySQL.

## Link pubblici

- Frontend: <https://esame-rumen-bortoletto.vercel.app>
- Backend API: <https://rumen.alwaysdata.net/api>

## Come usare l'applicazione

Aprire il link del frontend ed effettuare il login con uno degli utenti di prova.

Referente Academy:

```text
Email: academy@azienda.it
Password: Academy2026!
```

Dipendente:

```text
Email: marco.dipendente@azienda.it
Password: Dipendente2026!
```

Il referente Academy puo gestire corsi, dipendenti, assegnazioni e statistiche.
Il dipendente puo visualizzare i corsi assegnati e segnarli come completati.

## Avvio del backend in locale

E necessario avere PHP, Composer e MariaDB/MySQL. Il database locale deve essere
creato importando il file `backend/database/schema.sql`. Configurare poi i dati di
connessione nel file `backend/.env`.

Dal terminale, nella cartella principale del progetto:

```powershell
cd backend
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

Il backend sara disponibile all'indirizzo:

```text
http://127.0.0.1:8080/api
```

## Avvio del frontend in locale

Aprire un secondo terminale nella cartella principale del progetto:

```powershell
cd frontend
npm install
npm start
```

Aprire nel browser:

```text
http://localhost:4200
```

## Test delle API

La collection Postman pronta per il test si trova in:

```text
backend/docs/academy-api.postman_collection.json
```
