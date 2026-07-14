<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\JwtService;
use App\Database;
use App\Http\JsonResponse;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    private const ROLES = ['DIPENDENTE', 'REFERENTE_ACADEMY'];

    public function __construct(
        private readonly PDO $database,
        private readonly JwtService $jwtService
    ) {
    }

    public static function create(): self
    {
        return new self(Database::connection(), new JwtService());
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $errors = [];

        if ($email === '') {
            $errors['email'] = 'L’email è obbligatoria';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Inserisci un indirizzo email valido';
        }

        if ($password === '') {
            $errors['password'] = 'La password è obbligatoria';
        }

        if ($errors !== []) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Dati di accesso non validi',
                'errors' => $errors,
            ], 422);
        }

        $statement = $this->database->prepare(
            'SELECT id, nome, cognome, email, password, ruolo, attivo
             FROM utenti WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!is_array($user)
            || (int) $user['attivo'] !== 1
            || !password_verify($password, (string) $user['password'])
        ) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Email o password non corrette',
            ], 401);
        }

        unset($user['password'], $user['attivo']);
        $user['id'] = (int) $user['id'];

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Accesso effettuato con successo',
            'token' => $this->jwtService->createToken($user),
            'utente' => $user,
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $name = trim((string) ($data['nome'] ?? ''));
        $surname = trim((string) ($data['cognome'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $role = strtoupper(trim((string) ($data['ruolo'] ?? '')));
        $errors = [];

        if ($name === '') {
            $errors['nome'] = 'Il nome è obbligatorio';
        } elseif (mb_strlen($name) > 80) {
            $errors['nome'] = 'Il nome non può superare 80 caratteri';
        }

        if ($surname === '') {
            $errors['cognome'] = 'Il cognome è obbligatorio';
        } elseif (mb_strlen($surname) > 80) {
            $errors['cognome'] = 'Il cognome non può superare 80 caratteri';
        }

        if ($email === '') {
            $errors['email'] = 'L’email è obbligatoria';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Inserisci un indirizzo email valido';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'L’email non può superare 190 caratteri';
        }

        if ($password === '') {
            $errors['password'] = 'La password è obbligatoria';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'La password deve contenere almeno 8 caratteri';
        }

        if (!in_array($role, self::ROLES, true)) {
            $errors['ruolo'] = 'Il ruolo deve essere DIPENDENTE o REFERENTE_ACADEMY';
        }

        if ($errors !== []) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Impossibile creare l’utente: verifica i dati inseriti',
                'errors' => $errors,
            ], 422);
        }

        // La password non viene mai salvata in chiaro: viene trasformata in un hash sicuro.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Impossibile proteggere la password',
            ], 500);
        }

        try {
            $statement = $this->database->prepare(
                'INSERT INTO utenti (nome, cognome, email, password, ruolo)
                 VALUES (:nome, :cognome, :email, :password, :ruolo)'
            );
            $statement->execute([
                'nome' => $name,
                'cognome' => $surname,
                'email' => $email,
                'password' => $passwordHash,
                'ruolo' => $role,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return JsonResponse::send($response, [
                    'success' => false,
                    'message' => 'Esiste già un utente con questa email',
                    'errors' => ['email' => 'L’email deve essere univoca'],
                ], 409);
            }

            throw $exception;
        }

        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Utente creato con successo',
            'utente' => [
                'id' => (int) $this->database->lastInsertId(),
                'nome' => $name,
                'cognome' => $surname,
                'email' => $email,
                'ruolo' => $role,
            ],
        ], 201);
    }

    public function me(Request $request, Response $response): Response
    {
        $authenticatedUser = $request->getAttribute('auth');
        $statement = $this->database->prepare(
            'SELECT id, nome, cognome, email, ruolo FROM utenti
             WHERE id = :id AND attivo = 1 LIMIT 1'
        );
        $statement->execute(['id' => $authenticatedUser['id']]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return JsonResponse::send($response, [
                'success' => false,
                'message' => 'Utente autenticato non più disponibile',
            ], 401);
        }

        $user['id'] = (int) $user['id'];

        return JsonResponse::send($response, ['success' => true, 'utente' => $user]);
    }

    public function logout(Request $request, Response $response): Response
    {
        return JsonResponse::send($response, [
            'success' => true,
            'message' => 'Logout effettuato: elimina il token dal dispositivo',
        ]);
    }

    private function body(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }
}
