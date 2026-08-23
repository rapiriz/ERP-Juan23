<?php
// public/index.php

// Configuro las cabeceras para que nuestra API siempre responda en formato JSON y con codificación UTF-8.
header('Content-Type: application/json; charset=utf-8');

// Acá incorporo la clase de servicio que armé para manejar la lógica de negocio de los cobros en las rendiciones.
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeCobroRendicion.php';
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeRendicionReparto.php';
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeDiferenciaRendicion.php';
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeValidacionRendicion.php';
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeHistorialRendicion.php';
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeDevolucionRendicion.php';

use Dominio\Rendiciones\Services\ProcesadorDeCobroRendicion;
use Dominio\Rendiciones\Services\ProcesadorDeRendicionReparto;
use Dominio\Rendiciones\Services\ProcesadorDeDiferenciaRendicion;
use Dominio\Rendiciones\Services\ProcesadorDeValidacionRendicion;
use Dominio\Rendiciones\Services\ProcesadorDeHistorialRendicion;
use Dominio\Rendiciones\Services\ProcesadorDeDevolucionRendicion;

// Tomo el método HTTP (GET, POST, etc.) y la ruta que está pidiendo el cliente o el frontend.
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Defino el endpoint específico para registrar un cobro dentro de una rendición, tal como lo pide nuestra historia de usuario.
if ($method === 'POST' && $path === '/api/v1/rendiciones/cobros') {
    // Leo y decodifico el JSON que me llega en el cuerpo (body) de la petición HTTP.
    $input = json_decode(file_get_contents('php://input'), true);

    // Instancio mi servicio y le paso los datos crudos para que se encargue de procesarlos y validarlos.
    $procesador = new ProcesadorDeCobroRendicion();
    $resultado = $procesador->registrarCobro($input ?? []);

    // Configuro el código de estado HTTP que me devolvió la lógica de negocio y respondo en formato JSON.
    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint para registrar la rendición completa del reparto (RENDICIÓN #14)
if ($method === 'POST' && $path === '/api/v1/rendiciones') {
    $input = json_decode(file_get_contents('php://input'), true);

    $procesador = new ProcesadorDeRendicionReparto();
    $resultado = $procesador->registrarRendicionReparto($input ?? []);

    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint para registrar diferencias en una rendición (RENDICIÓN #12)
if ($method === 'POST' && $path === '/api/v1/rendiciones/diferencias') {
    $input = json_decode(file_get_contents('php://input'), true);

    $procesador = new ProcesadorDeDiferenciaRendicion();
    $resultado = $procesador->registrarDiferencia($input ?? []);

    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint para aprobar o rechazar rendiciones (RENDICIÓN #11)
if ($method === 'POST' && $path === '/api/v1/rendiciones/validar') {
    $input = json_decode(file_get_contents('php://input'), true);

    $procesador = new ProcesadorDeValidacionRendicion();
    $resultado = $procesador->validarRendicion($input ?? []);

    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint para consultar el historial de rendiciones con filtros (RENDICIÓN #10)
if ($method === 'GET' && $path === '/api/v1/rendiciones/historial') {
    // Para consultas GET, leemos los parámetros de la URL (query parameters)
    $filtros = [
        "fecha" => $_GET['fecha'] ?? null,
        "id_repartidor" => $_GET['id_repartidor'] ?? null,
        "estado" => $_GET['estado'] ?? null,
    ];

    $procesador = new ProcesadorDeHistorialRendicion();
    $resultado = $procesador->consultarHistorial($filtros);

    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint para registrar devoluciones dentro de una rendición (RENDICIÓN #8)
if ($method === 'POST' && $path === '/api/v1/rendiciones/devoluciones') {
    $input = json_decode(file_get_contents('php://input'), true);

    $procesador = new ProcesadorDeDevolucionRendicion();
    $resultado = $procesador->registrarDevolucion($input ?? []);

    http_response_code($resultado['codigo']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}

// Si entran a la raíz sin pedir ninguna ruta específica, devuelvo un mensaje simple para chequear que el servidor responde.
echo json_encode([
    "error" => false,
    "codigo" => 200,
    "mensaje" => "API del ERP funcionando correctamente en el módulo de Rendiciones."
]);