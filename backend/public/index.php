<?php

declare(strict_types=1);

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    ($_ENV['APP_ENV'] ?? 'production') !== 'production',
    true,
    true
);

// Consente al frontend Angular di chiamare l'API anche da un dominio diverso.
$app->add(function (Request $request, RequestHandler $handler): Response {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

$json = static function (Response $response, array $data, int $status = 200): Response {
    $response->getBody()->write(json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ));

    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus($status);
};

$app->options('/{routes:.*}', static fn (
    Request $request,
    Response $response
): Response => $response);

$app->get('/', static fn (
    Request $request,
    Response $response
): Response => $json($response, [
    'success' => true,
    'message' => 'API PHP Slim online',
    'endpoints' => [
        '/api/test',
        '/api/health',
    ],
]));

$app->get('/api/test', static fn (
    Request $request,
    Response $response
): Response => $json($response, [
    'success' => true,
    'message' => 'Frontend Angular collegato al backend PHP Slim',
]));

$app->get('/api/health', static function (
    Request $request,
    Response $response
) use ($json): Response {
    $database = 'not_configured';

    if (Database::isConfigured()) {
        try {
            Database::connection()->query('SELECT 1');
            $database = 'online';
        } catch (Throwable) {
            $database = 'offline';
        }
    }

    return $json($response, [
        'success' => $database !== 'offline',
        'services' => [
            'api' => 'online',
            'database' => $database,
        ],
    ], $database === 'offline' ? 503 : 200);
});

$app->run();
