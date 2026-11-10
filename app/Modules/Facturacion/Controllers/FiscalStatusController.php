<?php

namespace App\Modules\Facturacion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facturacion\Services\FiscalReadiness;
use Illuminate\Http\Request;

class FiscalStatusController extends Controller
{
    public function __invoke(Request $request, FiscalReadiness $readiness)
    {
        $status = $readiness->status();
        return $request->expectsJson() ? response()->json(['data' => $status])->header('Cache-Control', 'no-store')
            : view('operaciones.fiscal', ['status' => $status]);
    }
}
