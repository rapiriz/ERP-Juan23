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

    // GET /api/promociones/{id}
    // Devuelve los detalles de una promoción en particular
    public function show($id)
    {
        // Simulamos que fuimos a la base de datos y encontramos la promo con este ID
        $promocionSimulada = [
            'id_promocion'   => (int) $id,
            'nombre'         => 'Promoción Recuperada ' . $id,
            'tipo_descuento' => 'porcentaje',
            'valor'          => 20,
            'vigencia_desde' => '2026-10-01',
            'vigencia_hasta' => '2026-10-31',
            'condiciones'    => 'Condición de prueba',
            'estado'         => 'activa',
            'productos'      => [
                ['id_producto' => 15, 'cantidad' => 2]
            ]
        ];

        return response()->json($promocionSimulada, 200);
    }

    // PUT /api/promociones/{id}
    // Recibe nuevos datos y "actualiza" la promoción
    public function update(Request $request, $id)
    {
        // Tomamos los datos que envió el frontend
        $datosActualizados = $request->all();

        // Le forzamos el ID de la URL para confirmar que "editamos" la correcta
        $datosActualizados['id_promocion'] = (int) $id;

        return response()->json([
            'mensaje'   => "Promoción {$id} actualizada exitosamente (Modo Simulado)",
            'promocion' => $datosActualizados
        ], 200);
    }

    // DELETE /api/promociones/{id}
    // Realiza una BAJA LÓGICA (Cambia el estado a inactiva)
    public function destroy($id)
    {
        // En la base de datos real haríamos:
        // $promocion = Promocion::find($id);
        // $promocion->estado = 'inactiva';
        // $promocion->save();

        return response()->json([
            'mensaje' => "Promoción {$id} dada de baja exitosamente (Modo Simulado)",
            'promocion' => [
                'id_promocion' => (int) $id,
                'estado' => 'inactiva' // Simulamos que el estado cambió
            ]
        ], 200);
    }

    // PATCH /api/promociones/{id}/estado
    // Reactiva o desactiva una promoción según el valor enviado
    public function updateEstado(Request $request, $id)
    {
        // Tomamos el estado que manda el frontend, si no manda nada, asumimos 'activa'
        $nuevoEstado = $request->input('estado', 'activa');

        // En la base de datos real haríamos el mismo update de estado aquí

        return response()->json([
            'mensaje' => "El estado de la promoción {$id} ha sido cambiado a '{$nuevoEstado}' (Modo Simulado)",
            'promocion' => [
                'id_promocion' => (int) $id,
                'estado' => $nuevoEstado
            ]
        ], 200);
    }
}
