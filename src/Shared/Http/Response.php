<?php
namespace Shared\Http;

/**
 * Helper para responder en el formato JSON estándar acordado:
 * { "data": ..., "error": bool, "mensaje": "..." }
 */
class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['data' => $data, 'error' => false]);
    }

    public static function error(string $mensaje, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'codigo' => $status, 'mensaje' => $mensaje]);
    }
}
