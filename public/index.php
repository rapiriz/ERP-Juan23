<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Headers CORS para permitir pruebas locales y paneles web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Redireccionar raíz a la pantalla de entregas
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($uri === '/') {
    header("Location: /entregas.html");
    exit;
}

// Si existe el vendor autoloader de Composer, cargarlo
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

// Autoloader PSR-4 de respaldo para src/ (App\ y Dominio\)
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => __DIR__ . '/../src/',
        'Dominio\\' => __DIR__ . '/../src/'
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Si se llama directamente a los endpoints de Rendiciones (Módulo de Nicolás)
if (str_starts_with($uri, '/api/v1/rendiciones')) {
    header('Content-Type: application/json; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($method === 'POST' && $uri === '/api/v1/rendiciones/cobros') {
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeCobroRendicion();
        $resultado = $procesador->registrarCobro($input);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $uri === '/api/v1/rendiciones') {
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeRendicionReparto();
        $resultado = $procesador->registrarRendicionReparto($input);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $uri === '/api/v1/rendiciones/diferencias') {
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeDiferenciaRendicion();
        $resultado = $procesador->registrarDiferencia($input);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $uri === '/api/v1/rendiciones/validar') {
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeValidacionRendicion();
        $resultado = $procesador->validarRendicion($input);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $uri === '/api/v1/rendiciones/historial') {
        $filtros = [
            "fecha" => $_GET['fecha'] ?? null,
            "id_repartidor" => $_GET['id_repartidor'] ?? null,
            "estado" => $_GET['estado'] ?? null,
        ];
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeHistorialRendicion();
        $resultado = $procesador->consultarHistorial($filtros);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $uri === '/api/v1/rendiciones/devoluciones') {
        $procesador = new \Dominio\Rendiciones\Services\ProcesadorDeDevolucionRendicion();
        $resultado = $procesador->registrarDevolucion($input);
        http_response_code($resultado['codigo']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Si Laravel está instalado con vendor/autoload y bootstrap/app.php, arrancar con Laravel
if (file_exists(__DIR__ . '/../bootstrap/app.php') && class_exists(Application::class)) {
    if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
        require $maintenance;
    }
    /** @var Application $app */
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->handleRequest(Request::capture());
    exit;
}

// Modo Router Standalone
$router = new \App\Shared\Http\Router();
if (file_exists(__DIR__ . '/../routes/api.php')) {
    require_once __DIR__ . '/../routes/api.php';
}
$router->despachar();
