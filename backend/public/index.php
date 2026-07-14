<?php

declare(strict_types=1);

use App\Database;
use App\Controller\AuthController;
use App\Controller\AssignmentController;
use App\Controller\CourseController;
use App\Controller\EmployeeController;
use App\Controller\StatisticsController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Auth\JwtService;
use App\Http\JsonResponse;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(
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

$json = static fn (Response $response, array $data, int $status = 200): Response =>
    JsonResponse::send($response, $data, $status);

$errorMiddleware->setDefaultErrorHandler(static function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app): Response {
    $status = (int) $exception->getCode();
    $status = $status >= 400 && $status <= 599 ? $status : 500;
    $message = match ($status) {
        404 => 'Risorsa API non trovata',
        405 => 'Metodo HTTP non consentito per questa risorsa',
        default => 'Si è verificato un errore interno del server',
    };
    $data = ['success' => false, 'message' => $message];

    if ($displayErrorDetails && $status === 500) {
        $data['detail'] = $exception->getMessage();
    }

    return JsonResponse::send($app->getResponseFactory()->createResponse(), $data, $status);
});

$jwtService = new JwtService();
$authMiddleware = new AuthMiddleware($jwtService, Database::connection());
$academyOnly = new RoleMiddleware(['REFERENTE_ACADEMY']);
$employeeOnly = new RoleMiddleware(['DIPENDENTE']);
$authController = AuthController::create();
$courseController = new CourseController(Database::connection());
$employeeController = new EmployeeController(Database::connection());
$assignmentController = new AssignmentController(Database::connection());
$statisticsController = new StatisticsController(Database::connection());

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
        '/api/utenti/login',
        '/api/utenti/register',
        '/api/utenti/me',
        '/api/utenti/logout',
        '/api/utenti/dipendenti',
        '/api/corsi',
        '/api/assegnazioni-corsi',
        '/api/statistiche/academy',
    ],
]));

$app->get('/api/test', static fn (
    Request $request,
    Response $response
): Response => $json($response, [
    'success' => true,
    'message' => 'Frontend Angular collegato al backend PHP Slim',
]))->add($authMiddleware);

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
})->add($authMiddleware);

// Il login è l'unico endpoint applicativo accessibile senza un token.
$app->post('/api/utenti/login', [$authController, 'login']);

// La creazione degli utenti è protetta e riservata ai referenti Academy.
$app->post('/api/utenti/register', [$authController, 'register'])
    ->add($academyOnly)
    ->add($authMiddleware);

$app->get('/api/utenti/me', [$authController, 'me'])
    ->add($authMiddleware);

$app->post('/api/utenti/logout', [$authController, 'logout'])
    ->add($authMiddleware);

// Elenco dipendenti, catalogo e relative operazioni: solo referente Academy.
$app->get('/api/utenti/dipendenti', [$employeeController, 'index'])
    ->add($academyOnly)
    ->add($authMiddleware);

$app->get('/api/corsi', [$courseController, 'index'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->get('/api/corsi/{id:[0-9]+}', [$courseController, 'show'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->post('/api/corsi', [$courseController, 'create'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->put('/api/corsi/{id:[0-9]+}', [$courseController, 'update'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->put('/api/corsi/{id:[0-9]+}/disattiva', [$courseController, 'deactivate'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->delete('/api/corsi/{id:[0-9]+}', [$courseController, 'delete'])
    ->add($academyOnly)
    ->add($authMiddleware);

// Entrambi i ruoli possono leggere le assegnazioni; il controller limita
// automaticamente il dipendente ai soli dati di sua competenza.
$app->get('/api/assegnazioni-corsi', [$assignmentController, 'index'])
    ->add($authMiddleware);
$app->get('/api/assegnazioni-corsi/{id:[0-9]+}', [$assignmentController, 'show'])
    ->add($authMiddleware);

$app->post('/api/assegnazioni-corsi', [$assignmentController, 'create'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->put('/api/assegnazioni-corsi/{id:[0-9]+}', [$assignmentController, 'update'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->put('/api/assegnazioni-corsi/{id:[0-9]+}/annulla', [$assignmentController, 'cancel'])
    ->add($academyOnly)
    ->add($authMiddleware);
$app->delete('/api/assegnazioni-corsi/{id:[0-9]+}', [$assignmentController, 'cancel'])
    ->add($academyOnly)
    ->add($authMiddleware);

// Solo il dipendente può completare una propria assegnazione.
$app->put('/api/assegnazioni-corsi/{id:[0-9]+}/completa', [$assignmentController, 'complete'])
    ->add($employeeOnly)
    ->add($authMiddleware);

$app->get('/api/statistiche/academy', [$statisticsController, 'academy'])
    ->add($academyOnly)
    ->add($authMiddleware);

$app->run();
