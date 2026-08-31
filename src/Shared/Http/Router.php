<?php
namespace Shared\Http;

/**
 * Enrutador REST liviano y dinámico.
 * Soporta parámetros de URL como /api/v1/entregas/{id}
 */
class Router
{
    private array $rutas = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->agregarRuta('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->agregarRuta('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->agregarRuta('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void
    {
        $this->agregarRuta('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->agregarRuta('DELETE', $path, $handler);
    }

    private function agregarRuta(string $metodo, string $path, callable|array $handler): void
    {
        // Convertir /api/v1/entregas/{id} en regex: #^/api/v1/entregas/(?P<id>[^/]+)$#
        $patronRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $patronRegex = "#^" . rtrim($patronRegex, '/') . "$#";

        $this->rutas[] = [
            'metodo' => strtoupper($metodo),
            'path' => $path,
            'regex' => $patronRegex,
            'handler' => $handler
        ];
    }

    public function despachar(?string $uri = null, ?string $metodo = null): void
    {
        $uri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
        $metodo = $metodo ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Quitar query params (?zona=1...) y trailing slash
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // Obtener cuerpo JSON si existe
        $inputJSON = file_get_contents('php://input');
        $body = !empty($inputJSON) ? json_decode($inputJSON, true) : $_POST;

        foreach ($this->rutas as $ruta) {
            if ($ruta['metodo'] === strtoupper($metodo) && preg_match($ruta['regex'], $path, $coincidencias)) {
                // Extraer parámetros nombrados
                $params = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);

                try {
                    $handler = $ruta['handler'];
                    if (is_array($handler)) {
                        [$clase, $metodoAccion] = $handler;
                        $controlador = is_string($clase) ? new $clase() : $clase;
                        $controlador->$metodoAccion($params, $body, $_GET);
                    } else {
                        $handler($params, $body, $_GET);
                    }
                    return;
                } catch (\Exception $e) {
                    Response::error($e->getMessage(), 500);
                    return;
                }
            }
        }

        Response::error("Ruta no encontrada: [{$metodo}] {$path}", 404);
    }
}
