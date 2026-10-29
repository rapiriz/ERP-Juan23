<?php

namespace App\Http\Controllers;

use App\Services\MockProductoApi;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(private MockProductoApi $productos) {}

    // GET /productos
    public function index()
    {
        $productos = collect($this->productos->todos())
            ->map(fn ($p) => $this->formatearParaListado($p))
            ->values();

        return response()->json($productos, 200);
    }

    // GET /productos/{id}
    public function show($id)
    {
        $producto = $this->productos->buscar((int) $id);

        if ($producto === null) {
            return response()->json(['mensaje' => 'Producto no encontrado'], 404);
        }

        return response()->json($this->formatearParaDetalle($producto), 200);
    }

    // POST /productos
    public function store(Request $request)
    {
        $datos = $request->all();
        $datos['id_producto'] = rand(100, 999); // simulado

        return response()->json([
            'mensaje'  => 'Producto registrado exitosamente (Simulado)',
            'producto' => $datos,
        ], 201);
    }

    // PUT /productos/{id}
    public function update(Request $request, $id)
    {
        return response()->json([
            'mensaje'      => "Producto {$id} actualizado exitosamente (Simulado)",
            'datos_nuevos' => $request->all(),
        ], 200);
    }

    // PATCH /productos/{id}/estado
    public function updateEstado(Request $request, $id)
    {
        return response()->json([
            'mensaje'      => "Estado del producto {$id} modificado exitosamente (Simulado)",
            'nuevo_estado' => $request->input('estado', 'inactivo'),
        ], 200);
    }

    // -----------------------------------------------------------------
    // Helpers de formato
    // -----------------------------------------------------------------

    private function formatearParaListado(array $p): array
    {
        return [
            'id_producto'      => $p['id'],
            'codigo'           => $p['codigo'],
            'descripcion'      => $p['descripcion'] ?? $p['nombre'],
            'nombre_categoria' => $p['nombre_categoria'],
            'nombre_marca'     => $p['nombre_marca'],
            'precio_unitario'  => $p['precio_unitario'],
            'estado'           => $p['estado'],
            'fecha_alta'       => $p['fecha_alta'],
        ];
    }

    private function formatearParaDetalle(array $p): array
    {
        return [
            'id_producto'          => $p['id'],
            'codigo'               => $p['codigo'],
            'descripcion'          => $p['descripcion'] ?? $p['nombre'],
            'id_categoria'         => $p['id_categoria'],
            'nombre_categoria'     => $p['nombre_categoria'],
            'id_marca'             => $p['id_marca'],
            'nombre_marca'         => $p['nombre_marca'],
            'precio_unitario'      => $p['precio_unitario'],
            'estado'               => $p['estado'],
            'fecha_alta'           => $p['fecha_alta'],
            'fecha_modificacion'   => $p['fecha_modificacion'],
            'usuario_carga'        => $p['usuario_carga'],
            'usuario_modificacion' => $p['usuario_modificacion'],
        ];
    }
}
