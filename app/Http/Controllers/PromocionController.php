<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Promocion;
use Illuminate\Support\Facades\DB; // IMPORTANTE: Usaremos DB para la tabla intermedia

class PromocionController extends Controller
{
    // ==========================================
    // CATÁLOGO DE PRODUCTOS HARDCODEADOS
    // ==========================================
    private $catalogoSimulado = [
        1 => 'Yerba Mate Playadito 1kg',
        2 => 'Azúcar Ledesma 1kg',
        3 => 'Fideos Matarazzo 500g',
        4 => 'Aceite Natura 1.5L',
        5 => 'Coca cola' // Agregado para que tu prueba funcione tal cual
    ];

    // Función auxiliar: Busca el ID por nombre
    private function obtenerIdPorNombre($nombre)
    {
        $nombreBuscado = strtolower(trim($nombre));
        foreach ($this->catalogoSimulado as $id => $nombreCat) {
            if (strtolower($nombreCat) === $nombreBuscado) {
                return $id;
            }
        }
        // Si escriben algo que no está en la lista, lo convierte en un número seguro
        return abs(crc32($nombreBuscado));
    }

    // Función auxiliar: Busca el nombre por ID para enviarlo al frontend
    private function obtenerNombrePorId($id)
    {
        return $this->catalogoSimulado[$id] ?? 'Producto ID ' . $id;
    }

    // GET /api/promociones
    public function index()
    {
        $promociones = Promocion::all();

        $promocionesFormateadas = $promociones->map(function ($promo) {
            // Buscamos en la tabla intermedia sin necesidad del Modelo Producto
            $detallesPivot = DB::table('promocion_producto')
                ->where('id_promocion', $promo->id_promocion)
                ->get();

            $productosArray = $detallesPivot->map(function ($pivot) {
                return [
                    'id_producto' => $pivot->id_producto,
                    'nombre'      => $this->obtenerNombrePorId($pivot->id_producto), // Recuperamos el nombre hardcodeado
                    'cantidad'    => $pivot->cantidad
                ];
            });

            return [
                'id_promocion'   => $promo->id_promocion,
                'nombre'         => $promo->nombre,
                // MEMORIA INTELIGENTE: Si es menor a 0, recordamos que era porcentaje
                'tipo_descuento' => $promo->total < 0 ? 'porcentaje' : 'monto_fijo',
                'valor'          => abs($promo->total), // Quitamos el signo "menos" para la vista
                'vigencia_desde' => $promo->vigencia_desde,
                'vigencia_hasta' => $promo->vigencia_hasta,
                'condiciones'    => $promo->descripcion,
                'estado'         => $promo->estado == 1 ? 'activa' : 'inactiva',
                'productos'      => $productosArray
            ];
        });

        return response()->json($promocionesFormateadas, 200);
    }

    // POST /api/promociones
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // MEMORIA INTELIGENTE: Si es porcentaje, lo guardamos negativo. Si es plata, positivo.
            $valorRecibido = $request->input('valor', 0);
            $esPorcentaje = $request->input('tipo_descuento') === 'porcentaje';
            $totalConMemoria = $esPorcentaje ? -abs($valorRecibido) : abs($valorRecibido);

            $promocion = Promocion::create([
                'nombre'         => $request->input('nombre'),
                'descripcion'    => $request->input('descripcion') ?? $request->input('condiciones', ''),
                'total'          => $totalConMemoria, // Guardamos el número con el signo
                'vigencia_desde' => $request->input('vigencia_desde'),
                'vigencia_hasta' => $request->input('vigencia_hasta'),
                'estado'         => $request->input('estado', 'activa') === 'activa' ? 1 : 0
            ]);

            // Guardamos directamente en tu tabla intermedia promocion_producto
            foreach ($request->input('productos', []) as $prod) {
                $idProducto = $this->obtenerIdPorNombre($prod['nombre']);

                DB::table('promocion_producto')->insert([
                    'id_promocion' => $promocion->id_promocion,
                    'id_producto'  => $idProducto,
                    'cantidad'     => $prod['cantidad']
                ]);
            }
            DB::commit();

            return response()->json(['mensaje' => 'Promoción guardada (Híbrida)'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // GET /api/promociones/{id}
    public function show($id)
    {
        $promo = Promocion::find($id);
        if (!$promo) return response()->json(['mensaje' => 'No encontrada'], 404);

        $detallesPivot = DB::table('promocion_producto')->where('id_promocion', $id)->get();
        $productosArray = $detallesPivot->map(function ($pivot) {
            return [
                'id_producto' => $pivot->id_producto,
                'nombre'      => $this->obtenerNombrePorId($pivot->id_producto),
                'cantidad'    => $pivot->cantidad
            ];
        });

        $respuesta = [
            'id_promocion'   => $promo->id_promocion,
            'nombre'         => $promo->nombre,
            // MEMORIA INTELIGENTE: 
            'tipo_descuento' => $promo->total < 0 ? 'porcentaje' : 'monto_fijo',
            'valor'          => abs($promo->total),
            'vigencia_desde' => $promo->vigencia_desde,
            'vigencia_hasta' => $promo->vigencia_hasta,
            'condiciones'    => $promo->descripcion,
            'estado'         => $promo->estado == 1 ? 'activa' : 'inactiva',
            'productos'      => $productosArray
        ];

        return response()->json($respuesta, 200);
    }

    // PUT /api/promociones/{id}
    public function update(Request $request, $id)
    {
        $promocion = Promocion::find($id);
        if (!$promocion) return response()->json(['mensaje' => 'No encontrada'], 404);

        DB::beginTransaction();
        try {
            // MEMORIA INTELIGENTE: Misma lógica para cuando editas
            $valorRecibido = $request->input('valor', 0);
            $esPorcentaje = $request->input('tipo_descuento') === 'porcentaje';
            $totalConMemoria = $esPorcentaje ? -abs($valorRecibido) : abs($valorRecibido);

            $promocion->update([
                'nombre'         => $request->input('nombre'),
                'descripcion'    => $request->input('descripcion') ?? $request->input('condiciones', ''),
                'total'          => $totalConMemoria, // Guardamos el número con el signo
                'vigencia_desde' => $request->input('vigencia_desde'),
                'vigencia_hasta' => $request->input('vigencia_hasta'),
                'estado'         => $request->input('estado', 'activa') === 'activa' ? 1 : 0
            ]);

            // Eliminamos los productos viejos de la tabla intermedia
            DB::table('promocion_producto')->where('id_promocion', $id)->delete();

            // Insertamos los nuevos
            foreach ($request->input('productos', []) as $prod) {
                $idProducto = $this->obtenerIdPorNombre($prod['nombre']);

                DB::table('promocion_producto')->insert([
                    'id_promocion' => $promocion->id_promocion,
                    'id_producto'  => $idProducto,
                    'cantidad'     => $prod['cantidad']
                ]);
            }
            DB::commit();

            return response()->json(['mensaje' => "Promoción actualizada"], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // DELETE /api/promociones/{id}
    public function destroy($id)
    {
        $promocion = Promocion::find($id);
        if ($promocion) {
            $promocion->estado = 0;
            $promocion->save();
        }
        return response()->json(['mensaje' => "Promoción dada de baja"], 200);
    }

    // PATCH /api/promociones/{id}/estado
    public function updateEstado(Request $request, $id)
    {
        $promocion = Promocion::find($id);
        if ($promocion) {
            $promocion->estado = $request->input('estado', 'activa') === 'activa' ? 1 : 0;
            $promocion->save();
        }
        return response()->json(['mensaje' => "Estado actualizado"], 200);
    }
}
