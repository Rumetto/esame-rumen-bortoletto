<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CourseController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $filters = $request->getQueryParams();
        $where = [];
        $parameters = [];

        if (isset($filters['categoria']) && trim((string) $filters['categoria']) !== '') {
            $where[] = 'categoria = :categoria';
            $parameters['categoria'] = trim((string) $filters['categoria']);
        }

        if (isset($filters['attivo']) && $filters['attivo'] !== '') {
            $active = filter_var($filters['attivo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($active === null) {
                return JsonResponse::send($response, [
                    'success' => false,
                    'message' => 'Il filtro attivo deve essere true oppure false',
                ], 422);
            }

            $where[] = 'attivo = :attivo';
            $parameters['attivo'] = $active ? 1 : 0;
        }

        $sql = 'SELECT id, titolo, descrizione, categoria, durata_ore,
                       obbligatorio, attivo, creato_il, aggiornato_il
                FROM corsi';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY attivo DESC, categoria, titolo';
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $courses = array_map([$this, 'normalizeCourse'], $statement->fetchAll());

        return JsonResponse::send($response, [
            'success' => true,
            'totale' => count($courses),
            'corsi' => $courses,
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $course = $this->find((int) ($args['id'] ?? 0));

        if ($course === null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Corso non trovato',
            ], 404);
        }

        return JsonResponse::send($response, ['success' => true, 'corso' => $course]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        [$values, $errors] = $this->validate($data);

        if ($errors !== []) {
            return $this->validationError($response, $errors);
        }

        $statement = $this->database->prepare(
            'INSERT INTO corsi
                (titolo, descrizione, categoria, durata_ore, obbligatorio, attivo)
             VALUES
                (:titolo, :descrizione, :categoria, :durata_ore, :obbligatorio, :attivo)'
        );
        $statement->execute($values);
        $course = $this->find((int) $this->database->lastInsertId());

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso creato con successo',
            'corso' => $course,
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        if ($this->find($id) === null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Corso non trovato',
            ], 404);
        }

        [$values, $errors] = $this->validate($this->body($request));

        if ($errors !== []) {
            return $this->validationError($response, $errors);
        }

        $values['id'] = $id;
        $statement = $this->database->prepare(
            'UPDATE corsi SET
                titolo = :titolo,
                descrizione = :descrizione,
                categoria = :categoria,
                durata_ore = :durata_ore,
                obbligatorio = :obbligatorio,
                attivo = :attivo
             WHERE id = :id'
        );
        $statement->execute($values);

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso aggiornato con successo',
            'corso' => $this->find($id),
        ]);
    }

    public function deactivate(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        if ($this->find($id) === null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Corso non trovato',
            ], 404);
        }

        $statement = $this->database->prepare('UPDATE corsi SET attivo = 0 WHERE id = :id');
        $statement->execute(['id' => $id]);

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso disattivato con successo',
            'corso' => $this->find($id),
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        if ($this->find($id) === null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Corso non trovato',
            ], 404);
        }

        try {
            $statement = $this->database->prepare('DELETE FROM corsi WHERE id = :id');
            $statement->execute(['id' => $id]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return JsonResponse::send($response, [
                    'success' => false,
                    'message' => 'Il corso non può essere eliminato perché ha assegnazioni collegate',
                ], 409);
            }

            throw $exception;
        }

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso eliminato con successo',
        ]);
    }

    private function validate(array $data): array
    {
        $title = trim((string) ($data['titolo'] ?? ''));
        $description = trim((string) ($data['descrizione'] ?? ''));
        $category = trim((string) ($data['categoria'] ?? ''));
        $duration = filter_var($data['durata_ore'] ?? null, FILTER_VALIDATE_FLOAT);
        $mandatory = filter_var($data['obbligatorio'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $active = filter_var($data['attivo'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $errors = [];

        if ($title === '') {
            $errors['titolo'] = 'Il titolo è obbligatorio';
        } elseif (mb_strlen($title) > 160) {
            $errors['titolo'] = 'Il titolo non può superare 160 caratteri';
        }

        if ($description === '') {
            $errors['descrizione'] = 'La descrizione è obbligatoria';
        }

        if ($category === '') {
            $errors['categoria'] = 'La categoria è obbligatoria';
        } elseif (mb_strlen($category) > 100) {
            $errors['categoria'] = 'La categoria non può superare 100 caratteri';
        }

        if ($duration === false || $duration <= 0 || $duration > 999.99) {
            $errors['durata_ore'] = 'La durata deve essere un numero maggiore di zero';
        }

        if ($mandatory === null) {
            $errors['obbligatorio'] = 'Il valore obbligatorio deve essere booleano';
        }

        if ($active === null) {
            $errors['attivo'] = 'Il valore attivo deve essere booleano';
        }

        return [[
            'titolo' => $title,
            'descrizione' => $description,
            'categoria' => $category,
            'durata_ore' => $duration,
            'obbligatorio' => $mandatory ? 1 : 0,
            'attivo' => $active ? 1 : 0,
        ], $errors];
    }

    private function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->database->prepare(
            'SELECT id, titolo, descrizione, categoria, durata_ore,
                    obbligatorio, attivo, creato_il, aggiornato_il
             FROM corsi WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $course = $statement->fetch();

        return is_array($course) ? $this->normalizeCourse($course) : null;
    }

    private function normalizeCourse(array $course): array
    {
        $course['id'] = (int) $course['id'];
        $course['durata_ore'] = (float) $course['durata_ore'];
        $course['obbligatorio'] = (bool) $course['obbligatorio'];
        $course['attivo'] = (bool) $course['attivo'];

        return $course;
    }

    private function validationError(Response $response, array $errors): Response
    {
        return JsonResponse::send($response, [
            'success' => false,
            'message' => 'Dati del corso non validi',
            'errors' => $errors,
        ], 422);
    }

    private function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }
}
