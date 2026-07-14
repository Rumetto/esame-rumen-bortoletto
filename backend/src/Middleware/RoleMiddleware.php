<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly array $allowedRoles)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authenticatedUser = $request->getAttribute('auth');
        $role = is_array($authenticatedUser) ? ($authenticatedUser['ruolo'] ?? null) : null;

        if (!is_string($role) || !in_array($role, $this->allowedRoles, true)) {
            return JsonResponse::send(
                (new ResponseFactory())->createResponse(),
                ['success' => false, 'message' => 'Operazione non consentita per il ruolo corrente'],
                403
            );
        }

        return $handler->handle($request);
    }
}
