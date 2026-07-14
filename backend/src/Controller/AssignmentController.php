<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Repository\AssignmentRepository;
use App\Validation\AssignmentValidator;
use DateTimeImmutable;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AssignmentController
{
    private readonly AssignmentRepository $assignments;
    private readonly AssignmentValidator $validator;

    public function __construct(PDO $database)
    {
        $this->assignments = new AssignmentRepository($database);
        $this->validator = new AssignmentValidator();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->assignments->syncExpired();
        $user = $request->getAttribute('auth');
        [$filters, $errors] = $this->validator->filters(
            $request->getQueryParams(),
            $user['ruolo'] === 'REFERENTE_ACADEMY'
        );

        if ($errors !== []) {
            return $this->validationError($response, 'Filtri non validi', $errors);
        }

        $items = $this->assignments->search($filters, $user);

        return JsonResponse::send($response, [
            'success' => true,
            'totale' => count($items),
            'assegnazioni' => $items,
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $this->assignments->syncExpired();
        $assignment = $this->assignments->find((int) ($args['id'] ?? 0));

        if ($assignment === null) {
            return $this->notFound($response);
        }
        if (!$this->canAccess($request, $assignment)) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Non puoi accedere all’assegnazione di un altro dipendente',
            ], 403);
        }

        return JsonResponse::send($response, [
            'success' => true,
            'assegnazione' => $assignment,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        [$values, $errors] = $this->validator->payload($this->body($request));

        if ($errors !== []) {
            return $this->validationError($response, 'Dati dell’assegnazione non validi', $errors);
        }

        $relationError = $this->assignments->relationError(
            (int) $values['corso_id'],
            (int) $values['dipendente_id'],
            true
        );
        if ($relationError !== null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => $relationError,
            ], 422);
        }

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso assegnato con successo',
            'assegnazione' => $this->assignments->create($values),
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $current = $this->assignments->find($id);

        if ($current === null) {
            return $this->notFound($response);
        }

        [$values, $errors] = $this->validator->payload($this->body($request));
        if ($errors !== []) {
            return $this->validationError($response, 'Dati dell’assegnazione non validi', $errors);
        }

        $relationError = $this->assignments->relationError(
            (int) $values['corso_id'],
            (int) $values['dipendente_id'],
            (int) $values['corso_id'] !== $current['corso']['id']
        );
        if ($relationError !== null) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => $relationError,
            ], 422);
        }

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Assegnazione aggiornata con successo',
            'assegnazione' => $this->assignments->update($id, $values),
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($this->assignments->find($id) === null) {
            return $this->notFound($response);
        }

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Assegnazione annullata con successo',
            'assegnazione' => $this->assignments->cancel($id),
        ]);
    }

    public function complete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $assignment = $this->assignments->find($id);

        if ($assignment === null) {
            return $this->notFound($response);
        }
        if (!$this->canAccess($request, $assignment)) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Puoi completare soltanto i corsi assegnati a te',
            ], 403);
        }
        if ($assignment['stato'] === 'ANNULLATO') {
            return $this->conflict($response, 'Una assegnazione annullata non può essere completata');
        }
        if ($assignment['stato'] === 'COMPLETATO') {
            return $this->conflict($response, 'Il corso risulta già completato');
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($today < $assignment['data_assegnazione']) {
            return $this->conflict(
                $response,
                'Il corso non può essere completato prima della data di assegnazione'
            );
        }

        $employeeId = (int) $request->getAttribute('auth')['id'];

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Corso segnato come completato',
            'assegnazione' => $this->assignments->complete($id, $employeeId, $today),
        ]);
    }

    private function canAccess(Request $request, array $assignment): bool
    {
        $user = $request->getAttribute('auth');

        return $user['ruolo'] === 'REFERENTE_ACADEMY'
            || $assignment['dipendente']['id'] === $user['id'];
    }

    private function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function validationError(Response $response, string $message, array $errors): Response
    {
        return JsonResponse::send($response, [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    private function notFound(Response $response): Response
    {
        return JsonResponse::send($response, [
            'success' => false,
            'message' => 'Assegnazione non trovata',
        ], 404);
    }

    private function conflict(Response $response, string $message): Response
    {
        return JsonResponse::send($response, [
            'success' => false,
            'message' => $message,
        ], 409);
    }
}
