<?php

namespace App\Modules\Proveedores\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Proveedores\Services\SupplierPerformance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceController extends Controller
{
    public function index(Request $request, SupplierPerformance $service)
    {
        $filters = $request->validate(['desde' => 'nullable|date_format:Y-m-d',
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
            'id_proveedor' => 'nullable|integer|min:1']);
        $result = $service->report($filters);
        if ($request->expectsJson()) {
            return response()->json($result)->header('Cache-Control', 'no-store');
        }
        return view('proveedores.performance', [...$result, 'suppliers' => DB::table('proveedor')->orderBy('razon_social')->get()]);
    }
}
