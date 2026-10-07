<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductoController extends Controller
{
    // GET /productos[cite: 16]
    public function index()
    {
        $productos = [
            [
                'id_producto' => 1,
                'codigo' => 'PROD-001',
                'descripcion' => 'Yerba Mate Playadito 1kg',
                'nombre_categoria' => 'Almacén', //[cite: 16]
                'nombre_marca' => 'Playadito',   //[cite: 16]
                'precio_unitario' => 3500.00,    //[cite: 16]
                'estado' => 'activo',            //[cite: 16]
                'fecha_alta' => '2026-10-01'     //[cite: 16]
            ],
            [
                'id_producto' => 2,
                'codigo' => 'PROD-002',
                'descripcion' => 'Azúcar Ledesma 1kg',
                'nombre_categoria' => 'Almacén',
                'nombre_marca' => 'Ledesma',
                'precio_unitario' => 1200.00,
                'estado' => 'activo',
                'fecha_alta' => '2026-10-02'
            ]
        ];

        return response()->json($productos, 200);
    }

    // GET /productos/{id}[cite: 16]
    public function show($id)
    {
        return response()->json([
            'id_producto' => (int) $id,
            'codigo' => 'PROD-00' . $id,
            'descripcion' => 'Producto Simulado ' . $id,
            'id_categoria' => 1,                 //[cite: 16]
            'nombre_categoria' => 'Almacén',     //[cite: 16]
            'id_marca' => 2,                     //[cite: 16]
            'nombre_marca' => 'Marca Simulada',  //[cite: 16]
            'precio_unitario' => 2500.00,        //[cite: 16]
            'estado' => 'activo',                //[cite: 16]
            'fecha_alta' => '2026-10-06',        //[cite: 16]
            'fecha_modificacion' => null,        //[cite: 16]
            'usuario_carga' => 'Juan Pablo',     //[cite: 16]
            'usuario_modificacion' => null       //[cite: 16]
        ], 200);
    }

    // POST /productos[cite: 16]
    public function store(Request $request)
    {
        $datos = $request->all();
        $datos['id_producto'] = rand(100, 999); // Simulamos la creación del ID[cite: 16]

        return response()->json([
            'mensaje' => 'Producto registrado exitosamente (Simulado)',
            'producto' => $datos
        ], 201);
    }

    // PUT /productos/{id}[cite: 16]
    public function update(Request $request, $id)
    {
        return response()->json([
            'mensaje' => 'Producto ' . $id . ' actualizado exitosamente (Simulado)',
            'datos_nuevos' => $request->all()
        ], 200);
    }

    // PATCH /productos/{id}/estado[cite: 16]
    public function updateEstado(Request $request, $id)
    {
        return response()->json([
            'mensaje' => 'Estado del producto ' . $id . ' modificado exitosamente (Simulado)',
            'nuevo_estado' => $request->input('estado', 'inactivo') //[cite: 16]
        ], 200);
    }
}
