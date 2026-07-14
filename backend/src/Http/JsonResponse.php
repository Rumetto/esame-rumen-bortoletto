<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponse
{
    public static function send(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
