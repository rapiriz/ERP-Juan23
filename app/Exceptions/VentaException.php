<?php

namespace App\Exceptions;
// Namespace de la clase.
// Coincide con su ubicación app/Exceptions/VentaException.php.


use Exception;
// Importa la excepción base de PHP que esta clase va a extender.
use Illuminate\Http\JsonResponse;
// Importa el tipo de respuesta JSON que devolverá render().
use Illuminate\Http\Request;
// Importa la petición actual, que Laravel le entrega a render().

/**
 * VentaException  —  Error de NEGOCIO del módulo de ventas.
 *
 * ¿PARA QUÉ SIRVE?
 *   Cuando una regla de negocio no se cumple (ej: "stock insuficiente",
 *   "el carrito está vacío", "el cliente está inactivo") los Services lanzan
 *   esta excepción:
 *
 *       throw new VentaException('Stock insuficiente de Agua mineral 2L.');
 *
 *   Laravel detecta automáticamente el método render() de abajo y responde
 *   con un JSON y status HTTP 422, SIN que los controllers necesiten try/catch.
 *
 * FORMATO DE LA RESPUESTA (lo que recibe el FRONT):
 *   HTTP 422 (o el status indicado)
 *   {
 *     "ok": false,
 *     "mensaje": "Texto listo para mostrar al usuario",
 *     "errores": { ... detalle opcional, ej. problemas por línea ... }
 *   }
 *
 * LO USAN: CarritoService, VentaService, CajaService.
 */
class VentaException extends Exception
//Hereda de la excepción estándar de PHP, puede lanzarse con throw,
// y Laravel puede tratarla dentro de su sistema de manejo de errores.
{
    public function __construct(
        string $mensaje,
        protected int $status = 422,
        protected array $errores = []
    ) {
        // $mensaje es el texto principal que se mostrará al usuario.
        // $status controla el estado HTTP; por defecto es 422.
        // $errores agrega detalles opcionales, por ejemplo problemas por producto.

        parent::__construct($mensaje);
        // Guarda el mensaje en la clase Exception base.
        // Después se recupera con $this->getMessage().
    }

    /** Convierte la excepción en la respuesta JSON que consume el front. */
    public function render(Request $request): JsonResponse
    {
        // Laravel detecta este método y lo usa para convertir la excepción
        // en una respuesta HTTP. $request se recibe por convención de Laravel;
        // actualmente no se consulta dentro del método.

        return response()->json([
            'ok'      => false,
            // Indicador simple para que el frontend sepa que la operación falló.

            'mensaje' => $this->getMessage(),
            // Mensaje principal enviado al construir la excepción.

            'errores' => $this->errores,
            // Detalle opcional, vacío por defecto.
        ], $this->status);
        // El segundo argumento es el código HTTP, normalmente 422,
        // aunque una excepción puede crearse con otro status, como 404.
    }

    /**
     * Devolver true le dice a Laravel "ya me encargué del reporte": evita que
     * estos errores esperables (stock, validaciones de negocio) llenen
     * storage/logs/laravel.log. Los errores REALES (bugs, caída de la base)
     * siguen registrándose normalmente porque no son VentaException.
     */
    public function report(): bool
    {
        // Laravel llama a este método al decidir si debe reportar la excepción.
        return true;
        // Le indica a Laravel que la excepción ya fue atendida y que no la
        // registre como error normal. Sirve para no llenar el log con errores
        // esperables de negocio.
    }
}

/*
    El recorrido es:

    1.Llega una petición a una ruta del módulo de ventas.
    2.El controlador llama a un servicio, como CarritoService o VentaService.
    3.El servicio detecta un problema y lanza VentaException.
    4.La excepción sube hasta Laravel; el controlador no necesita atraparla.
    5.Laravel ejecuta render() y devuelve el JSON con el estado HTTP indicado.
*/
/*
Qué debe tener en cuenta el frontend
Para una VentaException, el frontend recibe un JSON como este:
{
  "ok": false,
  "mensaje": "El carrito está vacío.",
  "errores": {}
}

Importante: no todos los errores tienen este formato. La validación que hacen los controladores
con $request->validate() produce el formato estándar de Laravel, normalmente con las claves message
y errors. Para recibirlo como JSON, el frontend debe enviar Accept: application/json.
Un manejo robusto debería contemplar ambos formatos: mensaje/errores y message/errors.

Esta excepción siempre genera JSON desde render(), aunque no se haya enviado Accept:
application/json. No maneja errores de CSRF 419, fallos SQL ni errores de programación:
esos siguen el tratamiento general de Laravel.

*/
