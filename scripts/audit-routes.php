<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['router']->getRoutes();
Illuminate\Support\Facades\Artisan::call('route:list', ['--json' => true, '--except-vendor' => true]);
$routes = json_decode(Illuminate\Support\Facades\Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
$errors = [];
foreach ($routes as $route) {
    $action = $route['action'];
    if (! str_starts_with($action, 'App\\')) continue;
    [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');
    if (! class_exists($class) || ! method_exists($class, $method) || ! (new ReflectionMethod($class, $method))->isPublic()) {
        $errors[] = ['route' => $route['uri'], 'action' => $action];
    }
}
echo json_encode(['routes' => count($routes), 'errors' => $errors], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($errors ? 1 : 0);
