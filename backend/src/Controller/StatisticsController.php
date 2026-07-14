<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use App\Http\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StatisticsController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function academy(Request $request, Response $response): Response
    {
        $this->updateExpiredAssignments();
        $filters = $request->getQueryParams();
        $where = [];
        $parameters = [];
        $errors = [];

        if (isset($filters['mese']) && trim((string) $filters['mese']) !== '') {
            $month = trim((string) $filters['mese']);

            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                $errors['mese'] = 'Il mese deve avere formato YYYY-MM';
            } else {
                $start = new DateTimeImmutable($month . '-01');
                $where[] = 'a.data_assegnazione >= :mese_inizio';
                $where[] = 'a.data_assegnazione < :mese_fine';
                $parameters['mese_inizio'] = $start->format('Y-m-d');
                $parameters['mese_fine'] = $start->modify('+1 month')->format('Y-m-d');
            }
        } else {
            foreach (['data_inizio' => '>=', 'data_fine' => '<='] as $field => $operator) {
                if (isset($filters[$field]) && trim((string) $filters[$field]) !== '') {
                    $date = trim((string) $filters[$field]);

                    if (!$this->isDate($date)) {
                        $errors[$field] = "{$field} deve avere formato YYYY-MM-DD";
                    } else {
                        $where[] = "a.data_assegnazione {$operator} :{$field}";
                        $parameters[$field] = $date;
                    }
                }
            }

            if (isset($parameters['data_inizio'], $parameters['data_fine'])
                && $parameters['data_fine'] < $parameters['data_inizio']
            ) {
                $errors['data_fine'] = 'La fine del periodo non può precedere l’inizio';
            }
        }

        if (isset($filters['categoria']) && trim((string) $filters['categoria']) !== '') {
            $where[] = 'c.categoria = :categoria';
            $parameters['categoria'] = trim((string) $filters['categoria']);
        }

        if (isset($filters['dipendente_id']) && $filters['dipendente_id'] !== '') {
            $employeeId = filter_var($filters['dipendente_id'], FILTER_VALIDATE_INT);

            if ($employeeId === false || $employeeId <= 0) {
                $errors['dipendente_id'] = 'dipendente_id non valido';
            } else {
                $where[] = 'a.dipendente_id = :dipendente_id';
                $parameters['dipendente_id'] = $employeeId;
            }
        }

        if ($errors !== []) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Filtri delle statistiche non validi',
                'errors' => $errors,
            ], 422);
        }

        $sql = "SELECT
                    DATE_FORMAT(a.data_assegnazione, '%Y-%m') AS mese,
                    c.categoria,
                    COUNT(*) AS numero_assegnazioni,
                    SUM(CASE WHEN a.stato = 'COMPLETATO' THEN 1 ELSE 0 END) AS numero_completamenti,
                    ROUND(
                        SUM(CASE WHEN a.stato = 'COMPLETATO' THEN 1 ELSE 0 END)
                        * 100.0 / COUNT(*),
                        2
                    ) AS percentuale_completamento
                FROM assegnazioni_corsi a
                INNER JOIN corsi c ON c.id = a.corso_id";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY mese, c.categoria ORDER BY mese, c.categoria';
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $statistics = array_map(static fn (array $row): array => [
            'mese' => $row['mese'],
            'categoria' => $row['categoria'],
            'numeroAssegnazioni' => (int) $row['numero_assegnazioni'],
            'numeroCompletamenti' => (int) $row['numero_completamenti'],
            'percentualeCompletamento' => (float) $row['percentuale_completamento'],
        ], $statement->fetchAll());

        return JsonResponse::send($response, [
            'success' => true,
            'totale' => count($statistics),
            'statistiche' => $statistics,
        ]);
    }

    private function updateExpiredAssignments(): void
    {
        $this->database->exec(
            "UPDATE assegnazioni_corsi
             SET stato = 'SCADUTO'
             WHERE stato = 'ASSEGNATO' AND data_scadenza < CURRENT_DATE"
        );
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
