<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class EmployeeController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $statement = $this->database->query(
            "SELECT id, nome, cognome, email
             FROM utenti
             WHERE ruolo = 'DIPENDENTE' AND attivo = 1
             ORDER BY cognome, nome"
        );
        $employees = array_map(static function (array $employee): array {
            $employee['id'] = (int) $employee['id'];

            return $employee;
        }, $statement->fetchAll());

        return JsonResponse::send($response, [
            'success' => true,
            'totale' => count($employees),
            'dipendenti' => $employees,
        ]);
    }
}
