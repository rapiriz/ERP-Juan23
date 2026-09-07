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

// Autoloader PSR-4 de respaldo para src/ (App\)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

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
