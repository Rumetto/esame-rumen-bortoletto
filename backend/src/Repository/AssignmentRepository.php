<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AssignmentRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function search(array $filters, array $authenticatedUser): array
    {
        $where = [];
        $parameters = [];

        if ($authenticatedUser['ruolo'] === 'DIPENDENTE') {
            $where[] = 'a.dipendente_id = :utente_autenticato';
            $parameters['utente_autenticato'] = $authenticatedUser['id'];
        } elseif (isset($filters['dipendente_id'])) {
            $where[] = 'a.dipendente_id = :dipendente_id';
            $parameters['dipendente_id'] = $filters['dipendente_id'];
        }

        $filterColumns = [
            'stato' => 'a.stato',
            'categoria' => 'c.categoria',
            'corso_id' => 'a.corso_id',
        ];
        foreach ($filterColumns as $filter => $column) {
            if (isset($filters[$filter])) {
                $where[] = "{$column} = :{$filter}";
                $parameters[$filter] = $filters[$filter];
            }
        }

        foreach (['scadenza_da' => '>=', 'scadenza_a' => '<='] as $filter => $operator) {
            if (isset($filters[$filter])) {
                $where[] = "a.data_scadenza {$operator} :{$filter}";
                $parameters[$filter] = $filters[$filter];
            }
        }

        $sql = $this->detailQuery();
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.data_scadenza, c.titolo, u.cognome, u.nome';

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return array_map([$this, 'normalize'], $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->database->prepare($this->detailQuery() . ' WHERE a.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $assignment = $statement->fetch();

        return is_array($assignment) ? $this->normalize($assignment) : null;
    }

    public function create(array $values): array
    {
        $statement = $this->database->prepare(
            'INSERT INTO assegnazioni_corsi
                (corso_id, dipendente_id, data_assegnazione, data_scadenza,
                 stato, data_completamento)
             VALUES
                (:corso_id, :dipendente_id, :data_assegnazione, :data_scadenza,
                 :stato, :data_completamento)'
        );
        $statement->execute($values);

        return $this->find((int) $this->database->lastInsertId())
            ?? throw new \RuntimeException('Assegnazione creata ma non rileggibile');
    }

    public function update(int $id, array $values): array
    {
        $values['id'] = $id;
        $statement = $this->database->prepare(
            'UPDATE assegnazioni_corsi SET
                corso_id = :corso_id,
                dipendente_id = :dipendente_id,
                data_assegnazione = :data_assegnazione,
                data_scadenza = :data_scadenza,
                stato = :stato,
                data_completamento = :data_completamento
             WHERE id = :id'
        );
        $statement->execute($values);

        return $this->find($id) ?? throw new \RuntimeException('Assegnazione non rileggibile');
    }

    public function cancel(int $id): array
    {
        $statement = $this->database->prepare(
            "UPDATE assegnazioni_corsi
             SET stato = 'ANNULLATO', data_completamento = NULL
             WHERE id = :id"
        );
        $statement->execute(['id' => $id]);

        return $this->find($id) ?? throw new \RuntimeException('Assegnazione non rileggibile');
    }

    public function complete(int $id, int $employeeId, string $date): array
    {
        $statement = $this->database->prepare(
            "UPDATE assegnazioni_corsi
             SET stato = 'COMPLETATO', data_completamento = :data
             WHERE id = :id AND dipendente_id = :dipendente_id"
        );
        $statement->execute([
            'data' => $date,
            'id' => $id,
            'dipendente_id' => $employeeId,
        ]);

        return $this->find($id) ?? throw new \RuntimeException('Assegnazione non rileggibile');
    }

    public function relationError(int $courseId, int $employeeId, bool $courseMustBeActive): ?string
    {
        $statement = $this->database->prepare('SELECT attivo FROM corsi WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $courseId]);
        $course = $statement->fetch();

        if (!is_array($course)) {
            return 'Il corso selezionato non esiste';
        }
        if ($courseMustBeActive && (int) $course['attivo'] !== 1) {
            return 'Un corso non attivo non può essere usato per nuove assegnazioni';
        }

        $statement = $this->database->prepare(
            "SELECT id FROM utenti
             WHERE id = :id AND ruolo = 'DIPENDENTE' AND attivo = 1 LIMIT 1"
        );
        $statement->execute(['id' => $employeeId]);

        return is_array($statement->fetch())
            ? null
            : 'Il dipendente selezionato non esiste o non è attivo';
    }

    public function syncExpired(): void
    {
        $this->database->exec(
            "UPDATE assegnazioni_corsi
             SET stato = 'SCADUTO'
             WHERE stato = 'ASSEGNATO' AND data_scadenza < CURRENT_DATE"
        );
    }

    private function detailQuery(): string
    {
        return 'SELECT
                    a.id, a.corso_id, a.dipendente_id, a.data_assegnazione,
                    a.data_scadenza, a.stato, a.data_completamento,
                    c.titolo AS corso_titolo, c.descrizione AS corso_descrizione,
                    c.categoria AS corso_categoria, c.durata_ore AS corso_durata_ore,
                    c.obbligatorio AS corso_obbligatorio, c.attivo AS corso_attivo,
                    u.nome AS dipendente_nome, u.cognome AS dipendente_cognome,
                    u.email AS dipendente_email
                FROM assegnazioni_corsi a
                INNER JOIN corsi c ON c.id = a.corso_id
                INNER JOIN utenti u ON u.id = a.dipendente_id';
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'data_assegnazione' => $row['data_assegnazione'],
            'data_scadenza' => $row['data_scadenza'],
            'stato' => $row['stato'],
            'data_completamento' => $row['data_completamento'],
            'corso' => [
                'id' => (int) $row['corso_id'],
                'titolo' => $row['corso_titolo'],
                'descrizione' => $row['corso_descrizione'],
                'categoria' => $row['corso_categoria'],
                'durata_ore' => (float) $row['corso_durata_ore'],
                'obbligatorio' => (bool) $row['corso_obbligatorio'],
                'attivo' => (bool) $row['corso_attivo'],
            ],
            'dipendente' => [
                'id' => (int) $row['dipendente_id'],
                'nome' => $row['dipendente_nome'],
                'cognome' => $row['dipendente_cognome'],
                'email' => $row['dipendente_email'],
            ],
        ];
    }
}
