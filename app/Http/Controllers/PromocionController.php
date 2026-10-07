<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Promocion; // Importamos el modelo que creaste antes

class PromocionController extends Controller
{
    // Esta función se encarga de mostrar la pantalla principal de Promos
    public function index()
    {
        // 1. Buscamos todas las promociones en la base de datos
        $promociones = Promocion::all();

        // 2. Le enviamos esos datos a la vista (el frontend). 
        // Nota: asumo que el archivo de tu vista se llama 'promociones.blade.php'. 
        // Si se llama distinto, luego lo ajustamos.
        return view('promociones', compact('promociones'));
    }

    public function store(Request $request)
    {
        // 1. Guardar la promoción
        $promocion = Promocion::create([
            'codigo'         => $request->input('codigo'),
            'nombre'         => $request->input('nombre'),
            'tipo_descuento' => 'porcentaje',
            'valor'          => $request->input('descuento'),
            'estado'         => 'activa'
        ]);

        // 2. Guardar los productos (si el frontend los envía)
        $productos = $request->input('productos');
        if ($productos) {
            foreach ($productos as $producto) {
                $promocion->productos()->attach($producto['id_producto'], [
                    'cantidad' => $producto['cantidad']
                ]);
            }
        }

        // 3. Devolver un JSON de éxito
        return response()->json([
            'mensaje' => 'Promoción guardada exitosamente',
            'promocion' => $promocion
        ], 201);
    }
}
