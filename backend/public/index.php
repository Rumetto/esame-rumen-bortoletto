<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/api/test', function (
    Request $request,
    Response $response
): Response {
    $payload = json_encode([
        'success' => true,
        'message' => 'Backend PHP Slim funzionante',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $response->getBody()->write($payload);

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$app->run();
