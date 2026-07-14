
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS assegnazioni_corsi;
DROP TABLE IF EXISTS corsi;
DROP TABLE IF EXISTS utenti;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE utenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    cognome VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    ruolo ENUM('DIPENDENTE', 'REFERENTE_ACADEMY') NOT NULL,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_utenti_email UNIQUE (email),
    CONSTRAINT chk_utenti_nome CHECK (CHAR_LENGTH(TRIM(nome)) > 0),
    CONSTRAINT chk_utenti_cognome CHECK (CHAR_LENGTH(TRIM(cognome)) > 0),
    CONSTRAINT chk_utenti_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_utenti_password CHECK (CHAR_LENGTH(password) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE corsi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(160) NOT NULL,
    descrizione TEXT NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    durata_ore DECIMAL(5,2) UNSIGNED NOT NULL,
    obbligatorio TINYINT(1) NOT NULL DEFAULT 0,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_corsi_titolo CHECK (CHAR_LENGTH(TRIM(titolo)) > 0),
    CONSTRAINT chk_corsi_descrizione CHECK (CHAR_LENGTH(TRIM(descrizione)) > 0),
    CONSTRAINT chk_corsi_categoria CHECK (CHAR_LENGTH(TRIM(categoria)) > 0),
    CONSTRAINT chk_corsi_durata CHECK (durata_ore > 0),
    INDEX idx_corsi_categoria (categoria),
    INDEX idx_corsi_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assegnazioni_corsi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    corso_id INT UNSIGNED NOT NULL,
    dipendente_id INT UNSIGNED NOT NULL,
    data_assegnazione DATE NOT NULL,
    data_scadenza DATE NOT NULL,
    stato ENUM('ASSEGNATO', 'COMPLETATO', 'SCADUTO', 'ANNULLATO') NOT NULL DEFAULT 'ASSEGNATO',
    data_completamento DATE NULL,
    creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assegnazioni_corso
        FOREIGN KEY (corso_id) REFERENCES corsi(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assegnazioni_dipendente
        FOREIGN KEY (dipendente_id) REFERENCES utenti(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_assegnazioni_scadenza
        CHECK (data_scadenza >= data_assegnazione),
    CONSTRAINT chk_assegnazioni_completamento
        CHECK (data_completamento IS NULL OR data_completamento >= data_assegnazione),
    CONSTRAINT chk_assegnazioni_stato_completamento
        CHECK (
            (stato = 'COMPLETATO' AND data_completamento IS NOT NULL)
            OR (stato <> 'COMPLETATO' AND data_completamento IS NULL)
        ),
    INDEX idx_assegnazioni_corso (corso_id),
    INDEX idx_assegnazioni_dipendente (dipendente_id),
    INDEX idx_assegnazioni_stato (stato),
    INDEX idx_assegnazioni_date (data_assegnazione, data_scadenza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Credenziali di test:
-- Referente: academy@azienda.it / Academy2026!
-- Dipendenti: *.dipendente@azienda.it / Dipendente2026!
-- Gli hash seguenti sono generati con password_hash() di PHP.
-- La stessa logica è visibile nel metodo register di AuthController.php.
INSERT INTO utenti (id, nome, cognome, email, password, ruolo) VALUES
    (1, 'Laura', 'Bianchi', 'academy@azienda.it',
     '$2y$10$e6QBkTn9hKvBGcQofTJbTeL/enwqXZoZ73wNMCiq2oIckUo7MDehm', 'REFERENTE_ACADEMY'),
    (2, 'Marco', 'Rossi', 'marco.dipendente@azienda.it',
     '$2y$10$WK5vTXa8JHiWkN/ojoc.8uH.f41k2Ipkxwozu4932pj/fHy2CEaOe', 'DIPENDENTE'),
    (3, 'Giulia', 'Verdi', 'giulia.dipendente@azienda.it',
     '$2y$10$WK5vTXa8JHiWkN/ojoc.8uH.f41k2Ipkxwozu4932pj/fHy2CEaOe', 'DIPENDENTE'),
    (4, 'Luca', 'Romano', 'luca.dipendente@azienda.it',
     '$2y$10$WK5vTXa8JHiWkN/ojoc.8uH.f41k2Ipkxwozu4932pj/fHy2CEaOe', 'DIPENDENTE'),
    (5, 'Sara', 'Conti', 'sara.dipendente@azienda.it',
     '$2y$10$WK5vTXa8JHiWkN/ojoc.8uH.f41k2Ipkxwozu4932pj/fHy2CEaOe', 'DIPENDENTE');

INSERT INTO corsi (id, titolo, descrizione, categoria, durata_ore, obbligatorio, attivo) VALUES
    (1, 'Sicurezza sul lavoro',
     'Formazione obbligatoria sui rischi aziendali e sulle procedure di emergenza.',
     'Sicurezza', 8.00, 1, 1),
    (2, 'Protezione dei dati e GDPR',
     'Principi di protezione dei dati personali e comportamenti corretti in azienda.',
     'Compliance', 4.00, 1, 1),
    (3, 'Excel per l analisi dei dati',
     'Formule, tabelle pivot e strumenti pratici per analizzare dati aziendali.',
     'Competenze digitali', 6.50, 0, 1),
    (4, 'Comunicazione efficace',
     'Tecniche per comunicare in modo chiaro e collaborare nei gruppi di lavoro.',
     'Soft skill', 5.00, 0, 1),
    (5, 'Cybersecurity e phishing',
     'Riconoscimento delle minacce informatiche e prevenzione degli attacchi di phishing.',
     'Sicurezza', 3.00, 1, 1),
    (6, 'Fondamenti di project management',
     'Introduzione alla pianificazione, gestione dei rischi e controllo di progetto.',
     'Management', 10.00, 0, 1),
    (7, 'Archivio documentale precedente',
     'Corso storico conservato a fini di consultazione e non più assegnabile.',
     'Compliance', 2.00, 0, 0);


INSERT INTO assegnazioni_corsi
    (id, corso_id, dipendente_id, data_assegnazione, data_scadenza, stato, data_completamento)
VALUES
    (1, 1, 2, '2026-01-10', '2026-02-28', 'COMPLETATO', '2026-02-20'),
    (2, 2, 2, '2026-03-02', '2026-04-15', 'COMPLETATO', '2026-03-28'),
    (3, 3, 2, '2026-05-05', '2026-06-30', 'SCADUTO', NULL),
    (4, 5, 2, '2026-07-01', '2026-08-31', 'ASSEGNATO', NULL),
    (5, 1, 3, '2026-01-10', '2026-02-28', 'COMPLETATO', '2026-02-25'),
    (6, 2, 3, '2026-03-02', '2026-04-15', 'SCADUTO', NULL),
    (7, 4, 3, '2026-05-12', '2026-07-31', 'ASSEGNATO', NULL),
    (8, 6, 3, '2026-06-01', '2026-09-30', 'ANNULLATO', NULL),
    (9, 1, 4, '2026-01-15', '2026-03-15', 'SCADUTO', NULL),
    (10, 3, 4, '2026-04-01', '2026-05-31', 'COMPLETATO', '2026-05-20'),
    (11, 5, 4, '2026-05-04', '2026-06-30', 'COMPLETATO', '2026-06-18'),
    (12, 4, 4, '2026-07-03', '2026-09-15', 'ASSEGNATO', NULL),
    (13, 2, 5, '2026-02-02', '2026-03-31', 'COMPLETATO', '2026-03-10'),
    (14, 3, 5, '2026-04-10', '2026-06-10', 'ANNULLATO', NULL),
    (15, 5, 5, '2026-05-04', '2026-06-30', 'SCADUTO', NULL),
    (16, 6, 5, '2026-07-05', '2026-10-31', 'ASSEGNATO', NULL),
    (17, 1, 2, '2026-05-01', '2026-07-31', 'COMPLETATO', '2026-06-26'),
    (18, 1, 3, '2026-05-01', '2026-07-31', 'ASSEGNATO', NULL),
    (19, 5, 3, '2026-05-08', '2026-07-15', 'COMPLETATO', '2026-07-10'),
    (20, 5, 4, '2026-05-08', '2026-07-15', 'ASSEGNATO', NULL);
