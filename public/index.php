<?php
// public/index.php

// Configuro las cabeceras para que nuestra API siempre responda en formato JSON y con codificación UTF-8.
header('Content-Type: application/json; charset=utf-8');

// Acá incorporo la clase de servicio que armé para manejar la lógica de negocio de los cobros en las rendiciones.
require_once __DIR__ . '/../src/Rendiciones/Services/ProcesadorDeCobroRendicion.php';

use Dominio\Rendiciones\Services\ProcesadorDeCobroRendicion;

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

// Si entran a la raíz sin pedir ninguna ruta específica, devuelvo un mensaje simple para chequear que el servidor responde.
echo json_encode([
    "error" => false,
    "codigo" => 200,
    "mensaje" => "API del ERP funcionando correctamente en el módulo de Rendiciones."
]);