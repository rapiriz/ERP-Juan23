<?php
namespace App\Shared\Http;

/**
 * Enrutador REST liviano compatible con la definición de rutas.
 */
class Router
{
    private array $rutas = [];

    public function get(string $ruta, array $handler): void
    {
        $this->agregarRuta('GET', $ruta, $handler);
    }

    public function post(string $ruta, array $handler): void
    {
        $this->agregarRuta('POST', $ruta, $handler);
    }

    public function put(string $ruta, array $handler): void
    {
        $this->agregarRuta('PUT', $ruta, $handler);
    }

    public function patch(string $ruta, array $handler): void
    {
        $this->agregarRuta('PATCH', $ruta, $handler);
    }

    public function delete(string $ruta, array $handler): void
    {
        $this->agregarRuta('DELETE', $ruta, $handler);
    }

    private function agregarRuta(string $metodo, string $ruta, array $handler): void
    {
        $this->rutas[] = [
            'metodo' => strtoupper($metodo),
            'ruta' => $ruta,
            'controlador' => $handler[0],
            'accion' => $handler[1]
        ];
    }

    public function despachar(): void
    {
        $metodoPeticion = $_SERVER['REQUEST_METHOD'];
        $uriPeticion = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $queryParams = $_GET;

        // Si se pide una vista HTML o archivo estático, dejar que el servidor lo sirva
        if ($uriPeticion === '/' || str_ends_with($uriPeticion, '.html') || str_ends_with($uriPeticion, '.css') || str_ends_with($uriPeticion, '.js')) {
            if ($uriPeticion === '/') {
                header("Location: /entregas.html");
                exit;
            }
            return;
        }

        // Obtener cuerpo JSON si aplica
        $cuerpoPeticion = null;
        if (in_array($metodoPeticion, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $cuerpoPeticion = json_decode($input, true);
            }
        }

        foreach ($this->rutas as $rutaDefinida) {
            if ($rutaDefinida['metodo'] !== $metodoPeticion) {
                continue;
            }

            // Convertir /api/v1/entregas/{id} en regex #^/api/v1/entregas/(?P<id>[^/]+)$#
            $patronRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $rutaDefinida['ruta']);
            $patronRegex = '#^' . $patronRegex . '$#';

            if (preg_match($patronRegex, $uriPeticion, $coincidencias)) {
                $paramsRuta = [];
                foreach ($coincidencias as $clave => $valor) {
                    if (is_string($clave)) {
                        $paramsRuta[$clave] = $valor;
                    }
                }

                $claseControlador = $rutaDefinida['controlador'];
                $metodoControlador = $rutaDefinida['accion'];

                if (class_exists($claseControlador)) {
                    $instancia = new $claseControlador();
                    if (method_exists($instancia, $metodoControlador)) {
                        $instancia->$metodoControlador($paramsRuta, $cuerpoPeticion, $queryParams);
                        return;
                    }
                }
            }
        }

        Response::error("Ruta no encontrada: [{$metodoPeticion}] {$uriPeticion}", 404);
    }
}
