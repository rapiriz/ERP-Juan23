<?php

namespace App\Services\Ventas;

use App\Models\Venta; // Modelo usado para resumir cabeceras de ventas por usuario y fecha.
use Illuminate\Support\Facades\DB; // Query Builder para consultar caja_movimiento y cobro.

/**
 * CajaService  —  Datos para el botón "CERRAR CAJA" (arqueo del día).
 *
 * ¿QUÉ CALCULA?
 *   Para un usuario y una fecha: cuánto vendió, cuánto cobró por cada medio
 *   de pago, cuánto salió de caja (egresos) y cuánto EFECTIVO debería haber
 *   físicamente en la caja. Si el cajero informa cuánto efectivo contó,
 *   también devuelve la diferencia (sobrante / faltante).
 *
 * DE DÓNDE SALEN LOS DATOS:
 *   - caja_movimiento (ingresos/egresos) unido a cobro (para saber el medio de pago).
 *   - venta (cantidad y total de ventas del día por estado).
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Centraliza los cálculos del arqueo, para que CajaController solo valide la
 *   petición y devuelva el resultado. No recibe Request ni devuelve HTTP.
 *   VentaService alimenta caja_movimiento al cobrar; esos movimientos se vinculan
 *   a cobro, de donde se obtiene el medio de pago. Venta aporta cantidad y total.
 *
 * CONTRATO PARA EL FRONT (a través de CajaController):
 *   GET /ventas/caja/resumen?fecha=AAAA-MM-DD devuelve el arqueo esperado.
 *   POST /ventas/caja/cerrar acepta fecha y efectivo_contado, y devuelve además
 *   diferencia y tipo_diferencia. En ambos casos la respuesta está bajo "caja".
 *   El cierre es solo un cálculo: no guarda el arqueo ni impide más ventas.
 *
 * INTERPRETACIÓN:
 *   total vendido puede diferir de total_ingresos porque una venta confirmada
 *   puede quedar a cuenta corriente. efectivo_esperado = ingresos en efectivo
 *   menos egresos; no contempla saldo/apertura inicial y supone que todo egreso
 *   se paga en efectivo, porque caja_movimiento no identifica otro medio.
 *
 * LIMITACIÓN IMPORTANTE (de la base de datos, no del código):
 *   El esquema NO tiene una tabla de "cierres de caja" (apertura/cierre).
 *   Por eso el cierre es un ARQUEO DE CONSULTA: calcula y devuelve, pero no
 *   guarda que "la caja quedó cerrada". Si el negocio necesita ese registro,
 *   habría que sumar una tabla `cierre_caja` (ver documentacion-guia/VENTAS_BACKEND.md).
 *   Además, caja_movimiento no guarda el medio de los EGRESOS: se asume que
 *   salen de efectivo.
 *
 * LO USA: CajaController.
 */
class CajaService
{
    /**
     * @param int         $usuarioId       Cajero del que se hace el arqueo.
     * @param string|null $fecha           'YYYY-MM-DD'. null = hoy.
     * @param float|null  $efectivoContado Efectivo físico contado por el cajero (opcional).
     */
    public function resumen(int $usuarioId, ?string $fecha = null, ?float $efectivoContado = null): array
    {
        // Si no se pidió una fecha concreta, el arqueo usa el día actual de Laravel.
        $fecha = $fecha ?: now()->toDateString();

        // --- Movimientos de caja agrupados por tipo (ingreso/egreso) y medio de pago ---
        $movimientos = DB::table('caja_movimiento as cm')
            // Un cobro asociado aporta el medio de pago; LEFT JOIN conserva
            // movimientos que no tienen cobro (por ejemplo, egresos).
            ->leftJoin('cobro as c', 'c.id_cobro', '=', 'cm.id_cobro')
            // El arqueo se limita a esta fecha y a los movimientos de este usuario.
            ->where('cm.fecha', $fecha)
            ->where('cm.id_usuario', $usuarioId)
            // Si no hay cobro asociado, clasifica el movimiento como "otro".
            // cantidad se calcula, pero actualmente no se usa en el arreglo final.
            ->selectRaw("cm.tipo AS tipo, COALESCE(c.medio_pago, 'otro') AS medio_pago, SUM(cm.monto) AS total, COUNT(*) AS cantidad")
            ->groupBy('cm.tipo', DB::raw("COALESCE(c.medio_pago, 'otro')"))
            ->get();

        // Acumula ingresos por forma de pago y los totales generales.
        $ingresosPorMedio = [];
        $totalIngresos = $totalEgresos = 0.0;

        foreach ($movimientos as $m) {
            if ($m->tipo === 'ingreso') {
                // Agrupa los ingresos para que el front pueda mostrar efectivo,
                // tarjeta, transferencia, etc. con sus importes redondeados.
                $ingresosPorMedio[$m->medio_pago] = round((float) $m->total, 2);
                $totalIngresos += (float) $m->total;
            } else {
                // Cualquier tipo distinto de "ingreso" se suma como egreso.
                $totalEgresos += (float) $m->total;
            }
        }

        // --- Ventas del día (excluye las canceladas del total vendido) ---
        $ventas = Venta::where('fecha', $fecha)
            // Considera solo las ventas registradas por el mismo usuario.
            ->where('id_usuario', $usuarioId)
            // Agrupa por estado para contar y sumar canceladas por separado.
            ->selectRaw('estado, COUNT(*) AS cantidad, SUM(total) AS total')
            ->groupBy('estado')
            ->get();

        // Acumuladores de las ventas no canceladas y conteo independiente de anuladas.
        $cantidadVentas = 0;
        $totalVendido = 0.0;
        $canceladas = 0;
        foreach ($ventas as $v) {
            if ($v->estado === 'cancelada') {
                $canceladas = (int) $v->cantidad;
                // No sumar cantidad ni total de ventas canceladas.
                continue;
            }
            // Incluye otros estados, por ejemplo confirmada con saldo pendiente.
            $cantidadVentas += (int) $v->cantidad;
            $totalVendido   += (float) $v->total;
        }

        // --- Efectivo esperado en caja = ingresos en efectivo - egresos ---
        // El ?? 0 cubre el caso en que no hubo ingresos en efectivo.
        $efectivoEsperado = round(($ingresosPorMedio['efectivo'] ?? 0.0) - $totalEgresos, 2);

        // Arreglo de dominio que CajaController devuelve como JSON bajo "caja".
        $resultado = [
            'fecha'               => $fecha,
            'id_usuario'          => $usuarioId,
            'ventas'              => [
                'cantidad'   => $cantidadVentas,
                'canceladas' => $canceladas,
                'total'      => round($totalVendido, 2),
            ],
            'ingresos_por_medio'  => $ingresosPorMedio, // ['efectivo' => 12000.0, 'tarjeta' => ...]
            'total_ingresos'      => round($totalIngresos, 2),
            'total_egresos'       => round($totalEgresos, 2),
            'efectivo_esperado'   => $efectivoEsperado,
            // Sin efectivo contado no existe diferencia todavía.
            'efectivo_contado'    => $efectivoContado,
            'diferencia'          => null,               // contado - esperado
            'tipo_diferencia'     => null,               // 'sin_diferencia' | 'sobrante' | 'faltante'
        ];

        if ($efectivoContado !== null) {
            // Positivo = sobrante; negativo = faltante; cero = caja cuadrada.
            $dif = round($efectivoContado - $efectivoEsperado, 2);
            $resultado['diferencia']      = $dif;
            $resultado['tipo_diferencia'] = $dif == 0.0 ? 'sin_diferencia' : ($dif > 0 ? 'sobrante' : 'faltante');
        }

        // El servicio solo devuelve datos; no registra ni confirma un cierre.
        return $resultado;
    }
}
