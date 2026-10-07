<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// Comentamos la importación del modelo para asegurarnos de no tocar la base de datos
// use App\Models\Promocion; 

class PromocionController extends Controller
{
    public function index()
    {
        // Simulamos un listado estático en lugar de consultar Promocion::all()
        $promocionesSimuladas = [
            [
                'id_promocion'   => 1,
                'nombre'         => 'Descuento Fin de Semana',
                'tipo_descuento' => 'porcentaje',
                'valor'          => 15,
                'vigencia_desde' => '2026-10-10',
                'vigencia_hasta' => '2026-10-12',
                'condiciones'    => 'Aplica a todos los productos',
                'estado'         => 'activa'
            ]
        ];

        // Devolvemos JSON para la API (Si usabas Blade, lo cambiamos para que sea compatible con tu frontend)
        return response()->json($promocionesSimuladas, 200);
    }

    public function store(Request $request)
    {
        // 1. Simulamos el guardado de la promoción principal
        // Tomamos los nombres exactos que se envían en el JSON y le agregamos un ID ficticio
        $promocionSimulada = [
            'id_promocion'   => rand(100, 999), // ID generado al azar para simular la BD
            'nombre'         => $request->input('nombre'),
            'tipo_descuento' => $request->input('tipo_descuento'),
            'valor'          => $request->input('valor'),
            'vigencia_desde' => $request->input('vigencia_desde'),
            'vigencia_hasta' => $request->input('vigencia_hasta'),
            'condiciones'    => $request->input('condiciones'),
            'estado'         => $request->input('estado', 'activa')
        ];

        // 2. Simulamos la relación de productos (Tabla intermedia)
        // Simplemente tomamos lo que mandó el frontend y lo agregamos a la respuesta
        $productos = $request->input('productos', []);

        // Le adjuntamos los productos al arreglo simulado de la promoción
        $promocionSimulada['productos'] = $productos;

        // 3. Devolver la respuesta al frontend
        // Devolvemos un código 201 (Created) como si realmente se hubiera guardado
        return response()->json([
            'mensaje'   => 'Promoción guardada exitosamente (Modo Simulado)',
            'promocion' => $promocionSimulada
        ], 201);
    }
}
