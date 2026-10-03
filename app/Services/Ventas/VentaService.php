<?php

namespace App\Services\Ventas;

use App\Exceptions\VentaException; // Errores de negocio convertidos por Laravel en JSON.
use App\Models\Cliente; // Valida/bloquea al cliente y actualiza su saldo.
use App\Models\Cobro; // Guarda cada importe efectivamente aplicado a la venta.
use App\Models\DetalleVenta; // Guarda una fotografía de cada línea vendida.
use App\Models\Lote; // Descuenta inventario por vencimiento FEFO.
use App\Models\MovimientoStock; // Registra el historial del cambio de stock.
use App\Models\Producto; // Bloquea y actualiza stock de productos.
use App\Models\Venta; // Crea la cabecera de la operación.
use Illuminate\Support\Facades\DB; // Transacción y escrituras en tablas sin modelo Eloquent.

/**
 * VentaService  —  Botón "COBRAR": convierte el carrito en una VENTA real.
 *
 * ============================ QUÉ HACE, PASO A PASO ============================
 *  Todo ocurre dentro de UNA TRANSACCIÓN de MySQL: o se guarda TODO o no se
 *  guarda NADA (si algo falla a mitad de camino, la base queda como estaba).
 *
 *   1. Toma el estado actual del carrito (CarritoService::estado()).
 *   2. Valida los pagos recibidos (ver "REGLAS DE COBRO").
 *   3. Bloquea (lockForUpdate) los productos para que dos cajas no vendan el
 *      mismo stock a la vez, y re-verifica que haya stock.
 *   4. INSERT  venta                (cabecera; estado 'pagada' o 'confirmada')
 *   5. INSERT  detalle_venta        (una fila por línea del carrito)
 *   6. UPDATE  producto.stock       (descuenta)
 *      INSERT  movimiento_stock     (tipo 'venta', queda el historial)
 *      UPDATE  lote.cantidad        (descuenta primero el lote que vence antes)
 *   7. Por cada pago: INSERT cobro + cobro_venta + caja_movimiento (ingreso)
 *   8. Si quedó deuda y hay cliente: UPDATE cliente.saldo (+deuda)
 *   9. INSERT  log_auditoria        (quién creó la venta)
 *  10. Vacía el carrito de la sesión.
 *
 * ============================== REGLAS DE COBRO ===============================
 *  - Consumidor Final (sin cliente) DEBE pagar el total completo: no hay
 *    cuenta corriente para quien no es cliente registrado.
 *  - Un cliente registrado puede pagar de menos (o nada): la diferencia queda
 *    como deuda en cliente.saldo y la venta queda 'confirmada'.
 *  - Se puede pagar con varios medios a la vez (ej: 5000 efectivo + 3000 tarjeta).
 *  - Si se paga de MÁS, el excedente es VUELTO y solo puede salir de la parte
 *    en efectivo. A caja entra únicamente lo que realmente se queda (neto).
 *  - Estado resultante: pagado el total -> 'pagada'; si no -> 'confirmada'.
 *
 * ================================ NO INCLUYE ==================================
 *  - Facturación electrónica (AFIP/ARCA): venta.numFactura queda NULL. Cuando
 *    se integre, el punto de enganche es el paso 4 / después del commit.
 *  - Anulación de ventas (estado 'cancelada').
 *
 * DEPENDE DE : CarritoService, modelos Venta/DetalleVenta/Producto/Cliente/Cobro/
 *              MovimientoStock/Lote y tablas caja_movimiento, cobro_venta,
 *              unidad_medida y log_auditoria (vía DB::table).
 * LO USA     : VentaController@cobrar.
 *
 * IMPORTANCIA:
 *   Es el punto donde el carrito de sesión se convierte en registros persistentes.
 *   Coordina venta, detalle, stock, lotes, cobros, caja, deuda y auditoría para
 *   que las escrituras de base queden todas confirmadas o todas revertidas.
 *
 * GUÍA PARA EL FRONT:
 *   El front no llama este servicio directamente; envía POST /ventas/cobrar a
 *   VentaController. Debe enviar pagos como [{ medio_pago, monto }] y puede
 *   omitir pagos/observaciones. Mostrar la respuesta { ok, venta } y luego pedir
 *   GET /ventas/carrito para redibujar el carrito vacío.
 *   Deshabilitar el botón de cobro mientras se procesa la petición y no reintentar
 *   automáticamente ante timeout: este flujo no recibe una clave de idempotencia,
 *   así que repetir el POST podría registrar otra venta.
 *
 * ERRORES:
 *   VentaException representa reglas del dominio (carrito vacío, stock, vuelto,
 *   saldo) y responde { ok:false, mensaje, errores }. Los errores de validación
 *   del Controller usan el formato estándar Laravel { message, errors }.
 */
class VentaService
{
    public function __construct(private CarritoService $carrito)
    {
     // Laravel inyecta el servicio que recalcula el carrito y puede vaciarlo
     // después de que la transacción de venta se confirma.
    }

    /**
     * @param  array<int,array{medio_pago:string,monto:float|int|string}> $pagos  puede ser [] (todo a cuenta corriente)
     * @return array  Resumen para mostrar el ticket/confirmación (ver return al final).
     *
     * El Controller valida la forma/tipos del body antes de llamar este método.
     * Acá se aplican las reglas de negocio y se persiste el resultado.
     */
    public function cobrar(array $pagos, ?string $observaciones, int $usuarioId): array
    {
        // ---------- 1) Estado actual del carrito (recalculado desde la base) ----------
        // No se aceptan precios ni totales del navegador: CarritoService consulta
        // los precios, promociones y stock actuales y calcula el total del servidor.
        $estado = $this->carrito->estado();

        // No se permite crear ventas sin líneas.
        if (empty($estado['lineas'])) {
            throw new VentaException('El carrito está vacío.');
        }
        // Agrupa problemas por código para devolver al front qué líneas corregir.
        if (!$estado['puede_cobrar']) {
            $problemas = [];
            foreach ($estado['lineas'] as $l) {
                if ($l['problema']) {
                    $problemas[$l['codigo']] = $l['problema'];
                }
            }
            throw new VentaException('Hay productos con problemas. Corríjalos antes de cobrar.', 422, $problemas);
        }

        // Cliente, condición de consumidor final y total vienen del estado del servidor.
        $idCliente         = $estado['cliente']['id'];
        $esConsumidorFinal = $estado['cliente']['es_consumidor_final'];
        // Trabaja con centavos enteros para las comparaciones y sumas monetarias.
        $totalCent         = $this->aCentavos($estado['totales']['total']);

        // ---------- 2) Validar y repartir los pagos ----------
        // Trabajamos en CENTAVOS (enteros) para evitar errores de redondeo de decimales.
        $pagosCent = [];
        foreach ($pagos as $pago) {
            // Conserva el medio y normaliza el monto a centavos.
            $pagosCent[] = [
                'medio_pago' => $pago['medio_pago'],
                'centavos'   => $this->aCentavos((float) $pago['monto']),
            ];
        }

        // Total informado por el cliente y porción ofrecida en efectivo.
        $pagadoCent   = array_sum(array_column($pagosCent, 'centavos'));
        $efectivoCent = array_sum(array_column(
            array_filter($pagosCent, fn ($p) => $p['medio_pago'] === 'efectivo'),
            'centavos'
        ));

        // Consumidor Final no puede dejar saldo; solo un cliente identificado
        // puede quedar con diferencia a cuenta corriente.
        if ($esConsumidorFinal && $pagadoCent < $totalCent) {
            throw new VentaException('Consumidor Final debe abonar el total. Seleccione un cliente para dejar saldo a cuenta.');
        }

        // Si se recibió más que el total, la diferencia es vuelto.
        $vueltoCent = max(0, $pagadoCent - $totalCent);
        // El vuelto se entrega en efectivo: no se puede devolver saldo de tarjeta,
        // transferencia o cheque mediante este flujo.
        if ($vueltoCent > $efectivoCent) {
            throw new VentaException('El pago supera el total y el excedente (vuelto) solo puede devolverse en efectivo.');
        }

        // Aplicar los pagos al total: primero los NO efectivo, el efectivo al final
        // (así el efectivo absorbe el vuelto).
        // Aplica primero medios no efectivos y deja el efectivo al final, para
        // descontar el vuelto de la parte en efectivo y no registrar de más en caja.
        usort($pagosCent, fn ($a, $b) => ($a['medio_pago'] === 'efectivo') <=> ($b['medio_pago'] === 'efectivo'));

        // Distribuye lo recibido hasta cubrir el total; lo que quede sin cubrir es deuda.
        $restanteCent = $totalCent;
        $pagosAplicados = []; // lo que REALMENTE entra a caja
        foreach ($pagosCent as $pago) {
            // Nunca aplica a la venta más de lo que resta por pagar.
            $aplicado = min($pago['centavos'], $restanteCent);
            if ($aplicado > 0) {
                $pagosAplicados[] = ['medio_pago' => $pago['medio_pago'], 'centavos' => $aplicado];
                $restanteCent -= $aplicado;
            }
        }
        $pendienteCent = $restanteCent; // > 0 => queda deuda

        // ---------- 3) TRANSACCIÓN ----------
        // Todas las escrituras siguientes se confirman juntas; una excepción
        // dentro del callback provoca rollback automático de la base de datos.
        $resultado = DB::transaction(function () use (
            $estado, $idCliente, $observaciones, $usuarioId, $totalCent, $pagosAplicados, $pendienteCent, $vueltoCent
        ) {
            // Fecha comercial y timestamp de operación según la zona horaria de Laravel.
            $hoy   = now()->toDateString();
            $ahora = now();

            // 3.a) Bloquear productos y re-verificar stock (protege contra ventas simultáneas)
            // Bloqueo pesimista: serializa cobros concurrentes de los mismos productos.
            $ids = array_column($estado['lineas'], 'id_producto');
            $productos = Producto::whereIn('id_producto', $ids)->lockForUpdate()->get()->keyBy('id_producto');

            // El stock se vuelve a comprobar bajo bloqueo porque pudo cambiar
            // desde la última lectura del carrito.
            foreach ($estado['lineas'] as $linea) {
                $p = $productos->get($linea['id_producto']);
                if (!$p || $p->estado !== 'activo' || $p->stock < $linea['cantidad']) {
                    throw new VentaException("Stock insuficiente o producto inactivo: {$linea['descripcion']}.");
                }
            }

            // 3.b) Si hay cliente, bloquearlo y verificar que siga activo
            $cliente = null;
            if ($idCliente) {
                // Bloquea el cliente para evitar cambios concurrentes en su saldo/estado.
                $cliente = Cliente::lockForUpdate()->find($idCliente);
                if (!$cliente || $cliente->estado !== 'activo') {
                    throw new VentaException('El cliente seleccionado ya no está activo.');
                }
            }

            // 3.c) Cabecera de la venta
            // Inserta la cabecera; Consumidor Final se persiste con cliente NULL.
            $venta = Venta::create([
                'id_cliente'    => $idCliente,                       // NULL = Consumidor Final
                'fecha'         => $hoy,
                'total'         => $totalCent / 100,
                'numFactura'    => null,                             // pendiente: facturación electrónica
                'estado'        => $pendienteCent === 0 ? 'pagada' : 'confirmada',
                'observaciones' => $observaciones,
                'id_usuario'    => $usuarioId,
            ]);

            // 3.d) Líneas + stock + movimientos + lotes
            foreach ($estado['lineas'] as $linea) {
                // Congela en el detalle los valores utilizados en esta venta.
                DetalleVenta::create([
                    'id_venta'        => $venta->id_venta,
                    'id_producto'     => $linea['id_producto'],
                    'id_promocion'    => $linea['promocion']['id'] ?? null,
                    'cantidad'        => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento'       => $linea['descuento'],        // monto en $
                    'subtotal'        => $linea['subtotal'],
                ]);

                // Descontar stock
                // Actualiza el stock general del producto dentro de la transacción.
                $productos[$linea['id_producto']]->decrement('stock', $linea['cantidad']);

                // Historial de stock (cantidad positiva; el tipo 'venta' indica que resta)
                // Deja rastro de inventario; cantidad positiva y tipo=venta indican egreso.
                MovimientoStock::create([
                    'id_producto' => $linea['id_producto'],
                    'id_unidad'   => $this->unidadBase($linea['id_producto']),
                    'tipo'        => 'venta',
                    'cantidad'    => $linea['cantidad'],
                    'fecha'       => $hoy,
                    'motivo'      => "Venta #{$venta->id_venta}",
                    'id_usuario'  => $usuarioId,
                    'id_venta'    => $venta->id_venta,
                    'id_entrega'  => null,
                ]);

                // Si el producto maneja lotes, descuenta primero los de vencimiento más cercano.
                $this->descontarLotes($linea['id_producto'], $linea['cantidad'], $hoy);
            }

            // 3.e) Cobros + caja
            // Se crea un cobro por cada porción efectivamente aplicada; vuelto no se registra.
            $comprobantes = [];
            foreach ($pagosAplicados as $pago) {
                $monto = $pago['centavos'] / 100;

                // Inserta el comprobante de pago con los datos ya validados por Controller/Service.
                $cobro = Cobro::create([
                    'id_cliente'      => $idCliente,
                    'id_usuario'      => $usuarioId,
                    'fecha'           => $ahora,
                    'monto_total'     => $monto,
                    'medio_pago'      => $pago['medio_pago'],
                    'comprobante_nro' => null,
                    'estado'          => 'registrado',
                    'observaciones'   => "Cobro venta #{$venta->id_venta}",
                ]);

                // Número de comprobante con el mismo formato de los datos de prueba: REC-0001
                // Completa el número de recibo a partir de la PK generada por la base.
                $nro = sprintf('REC-%04d', $cobro->id_cobro);
                $cobro->update(['comprobante_nro' => $nro]);
                $comprobantes[] = $nro;

                // Tabla intermedia cobro <-> venta (no tiene modelo, va directo)
                // Vincula el pago con esta venta; un cobro puede asociarse a una venta.
                DB::table('cobro_venta')->insert([
                    'id_cobro'       => $cobro->id_cobro,
                    'id_venta'       => $venta->id_venta,
                    'monto_aplicado' => $monto,
                ]);

                // Alimenta el arqueo de caja con el importe neto retenido.
                DB::table('caja_movimiento')->insert([
                    'fecha'      => $hoy,
                    'monto'      => $monto,
                    'tipo'       => 'ingreso',
                    'concepto'   => "Cobro venta #{$venta->id_venta} ({$pago['medio_pago']})",
                    'id_usuario' => $usuarioId,
                    'id_cobro'   => $cobro->id_cobro,
                ]);
            }

            // 3.f) Deuda en cuenta corriente
            if ($cliente && $pendienteCent > 0) {
                // La deuda solo se acredita a un cliente registrado.
                $cliente->increment('saldo', $pendienteCent / 100);
            }

            // 3.g) Auditoría
            // Registra quién creó la venta y algunos valores iniciales para trazabilidad.
            DB::table('log_auditoria')->insert([
                'tabla_afectada'     => 'venta',
                'id_registro'        => $venta->id_venta,
                'accion'             => 'INSERT',
                'valores_anteriores' => null,
                'valores_nuevos'     => json_encode([
                    'estado' => $venta->estado, 'total' => $totalCent / 100, 'id_cliente' => $idCliente,
                ]),
                'id_usuario'         => $usuarioId,
                'fecha_hora'         => $ahora,
            ]);

            // Contrato que recibe VentaController y devuelve al frontend bajo "venta".
            // No incluye el modelo completo ni las líneas del detalle.
            return [
                'id_venta'     => $venta->id_venta,
                'fecha'        => $hoy,
                'estado'       => $venta->estado,
                'total'        => $totalCent / 100,
                'pagado'       => ($totalCent - $pendienteCent) / 100, // lo que quedó en caja
                'vuelto'       => $vueltoCent / 100,                   // a devolver al cliente
                'pendiente'    => $pendienteCent / 100,                // deuda (0 si pagó todo)
                'comprobantes' => $comprobantes,
            ];
        });

        // ---------- 4) Fuera de la transacción: ya está guardado, vaciamos el carrito ----------
        // Se limpia solo después del commit. El frontend debe vaciar/redibujar su vista
        // tras éxito; si hay timeout no debe reenviar automáticamente el mismo cobro.
        $this->carrito->limpiar();

        return $resultado;
    }

    // ======================================================================
    //  AUXILIARES PRIVADOS
    // ======================================================================

    /** Pesos -> centavos enteros (evita errores de coma flotante al sumar/comparar dinero). */
    private function aCentavos(float $monto): int
    {
        // Convierte pesos a centavos con redondeo monetario de dos decimales.
        return (int) round($monto * 100);
    }

    /**
     * Unidad "base" del producto (equivalencia_base = 1, ej: 'Unidad') para
     * rellenar movimiento_stock.id_unidad. Devuelve NULL si el producto no tiene.
     * (Hoy se vende siempre en unidades; vender por Pack/Caja sería una mejora futura.)
     */
    private function unidadBase(int $idProducto): ?int
    {
        // Obtiene la unidad de equivalencia 1; null es válido si el producto no tiene unidad configurada.
        return DB::table('unidad_medida')
            ->where('id_producto', $idProducto)
            ->where('equivalencia_base', 1)
            ->value('id_unidad');
    }

    /**
     * Descuenta de los LOTES vigentes (FEFO: first-expire-first-out, primero el
     * que vence antes). Los lotes ya vencidos no se tocan. Si el producto no
     * maneja lotes (la mayoría) simplemente no hay nada que descontar.
     */
    private function descontarLotes(int $idProducto, int $cantidad, string $hoy): void
    {
        // Carga lotes aptos en orden FEFO y los bloquea mientras dura la venta.
        $lotes = Lote::where('id_producto', $idProducto)
            ->where('estado', 'vigente')
            ->where('cantidad', '>', 0)
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', $hoy))
            ->orderByRaw('fecha_vencimiento IS NULL')   // los que no vencen, al final
            ->orderBy('fecha_vencimiento')
            ->orderBy('id_lote')
            ->lockForUpdate()
            ->get();

        // Distribuye el descuento entre uno o varios lotes hasta cubrir la cantidad solicitada.
        $restante = $cantidad;
        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }
            // Toma de este lote como máximo lo que queda por descontar.
            $toma = min($lote->cantidad, $restante);
            $lote->cantidad -= $toma;
            if ($lote->cantidad === 0) {
                $lote->estado = 'consumido';
            }
            $lote->save();
            $restante -= $toma;
        }
    }
}
