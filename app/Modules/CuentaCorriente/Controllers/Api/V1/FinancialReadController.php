<?php

namespace App\Modules\CuentaCorriente\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FinancialReadController extends Controller
{
    public function balance(int $id, FinancialReadService $finance): JsonResponse
    {
        Cliente::query()->findOrFail($id);

        return response()->json(['data' => $finance->account($id)['balance']])->header('Cache-Control', 'no-store');
    }

    public function debtors(Request $request, FinancialReadService $finance): JsonResponse
    {
        $rows = $finance->accounts()->map(fn ($account) => $account['balance'])
            ->filter(fn ($balance) => $balance['saldo'] > 0)->sortByDesc('saldo')->values();

        return $this->page($request, $rows);
    }

    public function invoices(Request $request, FinancialReadService $finance): JsonResponse
    {
        $rows = $finance->accounts()->flatMap(fn ($account) => $account['invoices'])->values();

        return $this->page($request, $rows);
    }

    public function payments(Request $request, FinancialReadService $finance): JsonResponse
    {
        $input = $request->validate(['cliente_id' => ['required', 'integer', 'min:1']]);
        Cliente::query()->findOrFail($input['cliente_id']);

        return $this->page($request, $finance->account((int) $input['cliente_id'])['payments']);
    }

    private function page(Request $request, Collection $rows): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $size = (int) ($input['per_page'] ?? 20);
        $page = (int) ($input['page'] ?? 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $size)->values(), $rows->count(), $size, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ['current_page' => $page, 'last_page' => $paginator->lastPage(), 'per_page' => $size, 'total' => $rows->count()],
        ])->header('Cache-Control', 'no-store');
    }
}
