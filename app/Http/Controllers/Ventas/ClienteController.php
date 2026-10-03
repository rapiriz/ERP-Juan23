<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller; // Base común de los controladores Laravel.
use App\Models\Cliente; // Modelo Eloquent conectado a la tabla cliente.
use App\Services\Ventas\CarritoService; // Mantiene sincronizados cliente y carrito de sesión.
use App\Support\UsuarioActual; // Obtiene el ID del operador desde sesión/configuración.
use Illuminate\Http\JsonResponse; // Tipo de respuesta JSON de los endpoints.
use Illuminate\Http\Request; // Petición HTTP: query string o body enviados por el front.

/**
 * ClienteController  —  Botones "BUSCAR CLIENTE" y "NUEVO CLIENTE (F1)".
 *
 * Solo contiene lo que el Punto de Venta necesita de clientes. El ABM completo
 * de clientes (editar, listar, desactivar) pertenece a otro módulo.
 *
 * ENDPOINTS
 *   GET  /ventas/clientes/buscar?q=...  -> buscar()
 *   POST /ventas/clientes               -> store()
 *
 * DEPENDE DE : modelo Cliente, CarritoService, UsuarioActual.
 *
 * FLUJO PARA EL FRONT:
 *   1. Buscar con GET /ventas/clientes/buscar?q=texto.
 *   2. Elegir un resultado con PUT /ventas/carrito/cliente (no con este controller).
 *   3. Para un alta rápida, enviar POST /ventas/clientes. Por defecto, el nuevo
 *      cliente también queda seleccionado y se devuelve el carrito recalculado.
 *
 * Las rutas están dentro del grupo web: las escrituras requieren sesión y CSRF.
 * Las reglas de negocio del carrito no se implementan acá, sino en CarritoService.
 */
class ClienteController extends Controller
{
    public function __construct(private CarritoService $carrito)
    {
     // Laravel inyecta el servicio; se necesita para seleccionar el cliente
     // recién creado y devolver el estado actualizado del carrito.
    }

    /**
     * GET /ventas/clientes/buscar?q=estrella
     * Busca clientes ACTIVOS por nombre, apellido/razón social o DNI/CUIT (coincidencia parcial).
     *
     * RESPUESTA:
     * { "ok": true, "clientes": [
     *     { "id_cliente": 2, "nombre": "Marcela Supermercado La Estrella SRL",
     *       "dni_cuit": "30-70123456-9", "condicion_iva": "Responsable Inscripto",
     *       "tipo_cliente": "mayorista", "domicilio": "Av. Colón 2500, Bahía Blanca",
     *       "saldo": 0.0 } ] }
     *
     * Para ELEGIR uno de los resultados el front llama a PUT /ventas/carrito/cliente.
     */
    public function buscar(Request $request): JsonResponse
    {
        // Lee el parámetro de URL ?q=... y quita espacios al principio/final.
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            // Evita consultar y devolver todo el padrón cuando la búsqueda está vacía.
            return response()->json(['ok' => true, 'clientes' => []]);
        }

        // Escapa comodines de LIKE (% y _) para que se interpreten como texto.
        // La consulta de Eloquent igualmente parametriza el valor enviado a SQL.
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $clientes = Cliente::activo()
            // El scope activo() filtra los clientes deshabilitados.
            ->where(fn ($w) => $w
                // Busca coincidencias parciales en estos tres campos.
                ->where('nombre', 'like', $like)
                ->orWhere('apellido_razon_social', 'like', $like)
                ->orWhere('dni_cuit', 'like', $like))
            // Ordena por apellido/razón social para que los resultados sean estables.
            ->orderBy('apellido_razon_social')
            // Limita resultados según config/ventas.php para no enviar el padrón entero.
            ->limit((int) config('ventas.max_resultados_busqueda'))
            ->get()
            // Convierte cada modelo a un objeto pequeño y apto para el selector del front.
            ->map(fn (Cliente $c) => [
                'id_cliente'    => $c->id_cliente,
                // nombre_completo y domicilio_completo son accessors del modelo Cliente.
                'nombre'        => $c->nombre_completo,
                'dni_cuit'      => $c->dni_cuit,
                'condicion_iva' => $c->condicion_iva,
                'tipo_cliente'  => $c->tipo_cliente,
                'domicilio'     => $c->domicilio_completo,
                // El modelo convierte saldo a número (float) mediante un cast.
                'saldo'         => $c->saldo,
            ]);

        // Devuelve siempre la misma envoltura, incluso cuando no hay coincidencias.
        return response()->json(['ok' => true, 'clientes' => $clientes]);
    }

    /**
     * POST /ventas/clientes     —  "NUEVO CLIENTE (F1)": alta rápida.
     *
     * BODY (JSON). Los campos obligatorios son los NOT NULL de la tabla `cliente`:
     * {
     *   "nombre": "Juan",                         // requerido
     *   "apellido_razon_social": "Pérez",         // requerido
     *   "dni_cuit": "20-11222333-4",              // requerido, ÚNICO
     *   "telefono": "291-5551234",                // requerido
     *   "email": "juan@correo.com",               // requerido, ÚNICO
     *   "direccion": "Alsina 100",                // requerido
     *   "tipo_cliente": "minorista",              // requerido: minorista | mayorista
     *   "localidad": "Bahía Blanca",              // opcional
     *   "condicion_iva": "Monotributo",           // opcional
     *   "id_zona": 1,                             // opcional (debe existir en `zona`)
     *   "seleccionar": true                       // opcional (default true): lo deja elegido en el carrito
     * }
     *
     * RESPUESTA OK (201): { "ok": true, "cliente": {id_cliente, nombre, dni_cuit, tipo_cliente},
     *                       "carrito": {...estado...} }
     * ERROR de validación (422): formato estándar de Laravel { "message": "...", "errors": { "campo": ["..."] } }
     *      (ej: "dni_cuit ya está en uso").
     */
    public function store(Request $request): JsonResponse
    {
        // Valida y extrae solo los campos admitidos antes de escribir en la base.
        // Si algo falla, Laravel responde 422 con su JSON estándar de validación.
        $datos = $request->validate([
            // Campos obligatorios con límites que deben coincidir con la base de datos.
            'nombre'                => ['required', 'string', 'max:120'],
            'apellido_razon_social' => ['required', 'string', 'max:160'],
            // Las reglas unique dan feedback temprano; la BD también debería
            // tener índices UNIQUE para evitar duplicados ante altas simultáneas.
            'dni_cuit'              => ['required', 'string', 'max:20', 'unique:cliente,dni_cuit'],
            'telefono'              => ['required', 'string', 'max:30'],
            'email'                 => ['required', 'email', 'max:160', 'unique:cliente,email'],
            'direccion'             => ['required', 'string', 'max:255'],
            // El tipo determina la lista de precios que usará el carrito.
            'tipo_cliente'          => ['required', 'in:minorista,mayorista'],
            // Campos opcionales; si id_zona se envía, debe existir en la tabla zona.
            'localidad'             => ['nullable', 'string', 'max:120'],
            'condicion_iva'         => ['nullable', 'string', 'max:80'],
            'id_zona'               => ['nullable', 'integer', 'exists:zona,id_zona'],
            // Controla si el alta rápida deja seleccionado al cliente en el carrito.
            'seleccionar'           => ['nullable', 'boolean'],
        ]);

        // Si el front no envía seleccionar, se asume true para el flujo de alta rápida.
        $seleccionar = $datos['seleccionar'] ?? true;
        // Este campo solo controla el flujo; no es una columna de cliente.
        unset($datos['seleccionar']);

        // Crea el cliente usando los campos validados y agrega valores del servidor:
        // estado inicial activo, saldo inicial cero y usuario creador.
        $cliente = Cliente::create($datos + [
            'estado'     => 'activo',
            'saldo'      => 0,
            'creado_por' => UsuarioActual::id(),
        ]);

        if ($seleccionar) {
            // Actualiza la sesión del carrito y cambia la lista a la del cliente.
            $this->carrito->seleccionarCliente($cliente->id_cliente);
        }

        // 201 indica que el recurso cliente fue creado correctamente.
        // Se devuelve el resumen mínimo del cliente y el estado completo del carrito.
        return response()->json([
            'ok'      => true,
            'cliente' => [
                'id_cliente'   => $cliente->id_cliente,
                'nombre'       => $cliente->nombre_completo,
                'dni_cuit'     => $cliente->dni_cuit,
                'tipo_cliente' => $cliente->tipo_cliente,
            ],
            'carrito' => $this->carrito->estado(),
        ], 201);
    }
}

/*
    Para el frontend:

 -Buscar: GET /ventas/clientes/buscar?q=texto. Devuelve clientes activos; si q está vacío, devuelve una lista vacía.
 -Elegir un resultado: PUT /ventas/carrito/cliente con {"id_cliente": 3}. Esta acción pertenece al CarritoController;
   seleccionar un cliente también cambia la lista de precios.
 -Alta rápida: POST /ventas/clientes con los campos obligatorios y, opcionalmente, "seleccionar": false. Por defecto
   el alta selecciona al cliente y responde 201 con el cliente y el carrito actualizado.
 -Para las peticiones JSON, enviar Accept: application/json; para el POST, también Content-Type: application/json y el
   token CSRF de la sesión.
*/
