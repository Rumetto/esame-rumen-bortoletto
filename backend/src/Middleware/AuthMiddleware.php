<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\JwtService;
use App\Http\JsonResponse;
use Firebase\JWT\ExpiredException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly PDO $database
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return $this->unauthorized('Token di autenticazione mancante');
        }

        try {
            $authenticatedUser = $this->jwtService->decodeToken($matches[1]);
        } catch (ExpiredException) {
            return $this->unauthorized('Token scaduto, effettua nuovamente il login');
        } catch (Throwable) {
            return $this->unauthorized('Token non valido');
        }

        $statement = $this->database->prepare(
            'SELECT id, email, ruolo FROM utenti WHERE id = :id AND attivo = 1 LIMIT 1'
        );
        $statement->execute(['id' => $authenticatedUser['id']]);
        $currentUser = $statement->fetch();

        if (!is_array($currentUser)) {
            return $this->unauthorized('Utente non disponibile o disattivato');
        }

        $authenticatedUser = [
            'id' => (int) $currentUser['id'],
            'email' => (string) $currentUser['email'],
            'ruolo' => (string) $currentUser['ruolo'],
        ];

        return $handler->handle($request->withAttribute('auth', $authenticatedUser));
    }

    private function unauthorized(string $message): Response
    {
        return JsonResponse::send(
            (new ResponseFactory())->createResponse(),
            ['success' => false, 'message' => $message],
            401
        );
    }
}
