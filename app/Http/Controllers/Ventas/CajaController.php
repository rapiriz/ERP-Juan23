<?php
// Indica que el archivo contiene código PHP.

namespace App\Http\Controllers\Ventas;
// Namespace de la clase. Su ruta y namespace permiten que Laravel la encuentre
// con el autoload PSR-4.

use App\Http\Controllers\Controller;
// Importa el controlador base de Laravel. La clase hereda de él.

use App\Services\Ventas\CajaService;
// Importa el servicio que consulta movimientos y calcula el resumen de caja.

use App\Support\UsuarioActual;
// Importa el helper que obtiene el ID del usuario que opera.

use Illuminate\Http\JsonResponse;
// Tipo declarado como retorno de los dos métodos públicos.

use Illuminate\Http\Request;
// Objeto con los datos recibidos en la petición.

/**
 * CajaController — Expone el resumen y el arqueo de caja del usuario actual.
 * El cierre es informativo: el esquema actual no persiste cierres de caja.
 *
 * La primera línea describe la responsabilidad del controlador.
 * La segunda advierte que cerrar actualmente no deja un registro permanente.
 */
class CajaController extends Controller
{
    public function __construct(private CajaService $caja)
    {
        // Laravel inyecta CajaService automáticamente al crear el controlador.
        // La propiedad privada $caja queda disponible para sus métodos.
    }

    /** GET /ventas/caja/resumen — Previsualiza el arqueo para una fecha. */
    public function resumen(Request $request): JsonResponse
    {
        $datos = $request->validate([
            // Permite omitir fecha o enviarla vacía/null.
            // Si se envía, exige el formato exacto AAAA-MM-DD.
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            // Forma estándar de éxito que puede interpretar el frontend.
            'ok'   => true,

            // Llama al servicio con:
            // 1. ID de usuario obtenido del servidor.
            // 2. Fecha solicitada o null si no se envió.
            // Si es null, CajaService usa la fecha actual.
            'caja' => $this->caja->resumen(
                UsuarioActual::id(),
                $datos['fecha'] ?? null
            ),
        ]);
        // Laravel convierte el arreglo en una respuesta JSON HTTP 200.
    }

    /** POST /ventas/caja/cerrar — Calcula diferencia sin persistir un cierre. */
    public function cerrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            // Fecha opcional con formato AAAA-MM-DD.
            'fecha' => ['nullable', 'date_format:Y-m-d'],

            // Efectivo contado opcional, numérico y no negativo.
            // Si no se informa, el servicio devuelve diferencia null.
            'efectivo_contado' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'ok' => true,

            'caja' => $this->caja->resumen(
                UsuarioActual::id(),
                // null significa que CajaService toma la fecha de hoy.
                $datos['fecha'] ?? null,

                // Si está informado, se convierte a float.
                // isset devuelve false si el campo falta o vale null.
                isset($datos['efectivo_contado'])
                    ? (float) $datos['efectivo_contado']
                    : null
            ),
        ]);
    }
}

/* Para qué sirve
CajaController.php es la capa HTTP del módulo de caja.
Recibe las peticiones del frontend, valida sus datos,
obtiene el usuario actual, llama a CajaService y devuelve JSON.

No realiza el cálculo ni guarda directamente los datos.
El cálculo pertenece a CajaService; las rutas que conectan cada
URL con sus métodos están en ventas.php.

Conexión con rutas y servicios:
1. En ventas.php, GET /ventas/caja/resumen apunta a CajaController::resumen().
2. En ese mismo archivo, POST /ventas/caja/cerrar apunta a CajaController::cerrar().
3. web.php incluye ventas.php; por eso las peticiones usan sesión y protección CSRF.
4. El controlador pasa los datos a CajaService::resumen().
5. El servicio consulta caja_movimiento, cobro y venta, y arma los números que el
controlador devuelve bajo la clave caja.

Qué debería enviar el frontend
Para consultar el resumen:
GET /ventas/caja/resumen?fecha=2026-10-03
Accept: application/json

La fecha es opcional; sin ella se usa el día actual.
Para enviar el arqueo:

POST /ventas/caja/cerrar
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: <token de la sesión>
{
  "fecha": "2026-10-03",
  "efectivo_contado": 1250.50
}

El ID de usuario no se envía desde el frontend.
UsuarioActual::id() lo obtiene del servidor —de la sesión o,
actualmente, de una configuración demo— para que el cliente no
pueda elegir a nombre de quién consultar la caja.

Qué respuestas esperar
Una respuesta exitosa tiene esta forma:
{
  "ok": true,
  "caja": {
    "fecha": "2026-10-03",
    "efectivo_esperado": 1200,
    "efectivo_contado": 1250.5,
    "diferencia": 50.5,
    "tipo_diferencia": "sobrante"
  }
}

La diferencia se calcula como efectivo contado menos efectivo esperado:
     positiva significa sobrante y negativa, faltante. Esa lógica está en CajaService.

Si la fecha tiene formato inválido o el efectivo contado es negativo,
$request->validate() detiene la ejecución antes de llamar al servicio. Laravel responde con
HTTP 422 y su formato estándar de validación (message y errors), que es distinto del formato
de VentaException (mensaje y errores). El frontend debería contemplar ambos.

Límites importantes
-cerrar() no persiste un cierre. Solo vuelve a calcular el resumen con el efectivo contado y devuelve la diferencia.
No bloquea nuevas ventas ni guarda un historial.
-El controlador no declara aquí middleware de autenticación.
Mientras siga activo el usuario demo de UsuarioActual, la separación de caja por operador no representa un login real.
-Los comentarios antiguos del ventas.php pueden decir que hay que borrar una ruta duplicada en web.php; el web.php
actual ya carga este archivo y no tiene esa ruta duplicada.
*/
