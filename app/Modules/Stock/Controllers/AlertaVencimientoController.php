<?php
declare(strict_types=1);

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\Lote;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * S09 - Alertas de Productos Próximos a Vencer.
 *
 * Detecta automáticamente lotes con fechas de vencimiento cercanas,
 * usando el umbral configurable `dias_alerta_vencimiento` de cada producto.
 * Genera alertas clasificadas por niveles (vencido, urgente, próximo).
 */
class AlertaVencimientoController extends Controller
{
    /**
     * Listar lotes con alerta de vencimiento activa.
     *
     * Para cada lote vigente, compara `dias_para_vencer` contra el
     * `dias_alerta_vencimiento` del producto al que pertenece.
     *
     * Niveles:
     *  - vencido:  dias_para_vencer < 0
     *  - urgente:  0 <= dias_para_vencer <= 15
     *  - proximo:  16 <= dias_para_vencer <= dias_alerta_vencimiento del producto
     *
     * @queryParam id_producto int  Filtrar por producto específico.
     * @queryParam nivel string     Filtrar por nivel: vencido|urgente|proximo.
     */
    public function index(Request $request): JsonResponse
    {
        $idProducto = $request->filled('id_producto') ? (int) $request->input('id_producto') : null;
        $filtroNivel = $request->input('nivel');

        $hoy = Carbon::today()->toDateString();

        // Traer lotes relevantes: vigentes dentro del umbral máximo + vencidos
        $query = Lote::with(['producto', 'unidad'])
            ->whereHas('producto', function ($q) {
                $q->where('estado', 'activo');
            })
            ->where(function ($q) use ($hoy) {
                // Lotes vigentes con fecha de vencimiento en el futuro cercano
                $q->where('estado', 'vigente')
                  // O lotes ya vencidos (estado vencido o fecha pasada)
                  ->orWhere('estado', 'vencido')
                  ->orWhere(function ($q2) use ($hoy) {
                      $q2->where('fecha_vencimiento', '<', $hoy);
                  });
            })
            ->orderBy('fecha_vencimiento', 'asc');

        if ($idProducto) {
            $query->where('id_producto', $idProducto);
        }

        $lotes = $query->get();

        // Clasificar cada lote según el umbral de su producto
        $alertas = [];
        $conteoVencidos = 0;
        $conteoUrgentes = 0;
        $conteoProximos = 0;

        foreach ($lotes as $lote) {
            $dias = $lote->dias_para_vencer;

            if ($dias === null) {
                continue; // Lote sin fecha de vencimiento, no aplica
            }

            $umbralProducto = $lote->producto
                ? ($lote->producto->dias_alerta_vencimiento ?? 30)
                : 30;

            // Determinar nivel de alerta
            $nivel = null;
            if ($dias < 0) {
                $nivel = 'vencido';
                $conteoVencidos++;
            } elseif ($dias <= 15) {
                $nivel = 'urgente';
                $conteoUrgentes++;
            } elseif ($dias <= $umbralProducto) {
                $nivel = 'proximo';
                $conteoProximos++;
            } else {
                continue; // No está en alerta
            }

            // Aplicar filtro por nivel si fue especificado
            if ($filtroNivel && $nivel !== $filtroNivel) {
                continue;
            }

            $alertas[] = [
                'id_lote'               => $lote->id_lote,
                'nro_lote'              => $lote->nro_lote,
                'id_producto'           => $lote->id_producto,
                'codigo_producto'       => $lote->producto?->codigo,
                'descripcion_producto'  => $lote->producto?->descripcion,
                'cantidad'              => $lote->cantidad,
                'nombre_unidad'         => $lote->unidad?->nombre_unidad,
                'fecha_vencimiento'     => $lote->fecha_vencimiento
                    ? $lote->fecha_vencimiento->format('Y-m-d')
                    : null,
                'dias_para_vencer'      => $dias,
                'nivel_alerta'          => $nivel,
                'umbral_producto'       => $umbralProducto,
                'estado_lote'           => $dias < 0 ? 'vencido' : (string) $lote->estado,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'resumen' => [
                    'vencidos'      => $conteoVencidos,
                    'urgentes'      => $conteoUrgentes,
                    'proximos'      => $conteoProximos,
                    'total_alertas' => $conteoVencidos + $conteoUrgentes + $conteoProximos,
                ],
                'alertas' => $alertas,
            ],
        ]);
    }

    /**
     * Configurar el umbral de días de alerta de vencimiento de un producto.
     *
     * @param int $id  ID del producto.
     */
    public function configurarUmbral(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dias_alerta_vencimiento' => 'required|integer|min:1|max:365',
        ], [
            'dias_alerta_vencimiento.required' => 'Debe indicar la cantidad de días para la alerta.',
            'dias_alerta_vencimiento.min'      => 'El umbral debe ser de al menos 1 día.',
            'dias_alerta_vencimiento.max'      => 'El umbral no puede superar los 365 días.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $producto->dias_alerta_vencimiento = (int) $request->input('dias_alerta_vencimiento');
        $producto->save();

        // Contar lotes actualmente en alerta para este producto
        $hoy = Carbon::today();
        $limite = $hoy->copy()->addDays($producto->dias_alerta_vencimiento);

        $lotesEnAlerta = Lote::where('id_producto', $producto->id_producto)
            ->where('estado', 'vigente')
            ->where('fecha_vencimiento', '<=', $limite->toDateString())
            ->count();

        return response()->json([
            'status'  => 'success',
            'message' => "Umbral de alerta configurado a {$producto->dias_alerta_vencimiento} día(s).",
            'data'    => [
                'id_producto'             => $producto->id_producto,
                'codigo'                  => $producto->codigo,
                'descripcion'             => $producto->descripcion,
                'dias_alerta_vencimiento' => $producto->dias_alerta_vencimiento,
                'lotes_en_alerta'         => $lotesEnAlerta,
            ],
        ]);
    }
}
