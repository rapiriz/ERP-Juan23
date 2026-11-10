<?php

namespace App\Modules\Shared\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Services\FinancialIntegrityReview;
use App\Modules\Ventas\Services\StockDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperationsController extends Controller
{
    public function stock(Request $request)
    {
        $filters = $request->validate(['estado' => 'nullable|in:pendiente,aplicado,todas', 'page' => 'nullable|integer|min:1']);
        $state = $filters['estado'] ?? 'pendiente';
        $operations = DB::table('g4_stock_operations')->when($state !== 'todas', fn ($q) => $q->where('estado', $state))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $heartbeat = is_file($file = storage_path('app/private/operations/scheduler.json')) ? json_decode(file_get_contents($file), true) : null;

        return response()->view('operaciones.stock', compact('operations', 'state', 'heartbeat'))->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, int $id, StockDeliveryService $delivery)
    {
        DB::table('g4_stock_operations')->where('id', $id)->firstOrFail();
        $ok = $delivery->deliver($id);
        Log::notice('Reintento administrativo de stock', ['operacion' => $id, 'actor' => $request->user()->id, 'aplicado' => $ok]);

        return redirect()->route('operaciones.stock')->with($ok ? 'success' : 'error', $ok ? 'Operación aplicada. Su clave impide duplicar movimientos.' : 'La operación sigue pendiente. Revisá el error y la conexión antes de reintentar.');
    }

    public function historical(FinancialIntegrityReview $review)
    {
        return response()->view('operaciones.historicas', ['findings' => $review->findings(),
            'historicalTables' => app(\App\Modules\Shared\Services\HistoricalSchemaReview::class)->inventory()])->header('Cache-Control', 'private, no-store');
    }
}
