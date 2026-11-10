<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Modules\ConciliacionBancaria\Services\ChequeService;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Http\Response;
use Illuminate\Http\Request;

/**
 * Controller de Cheques (dentro de Conciliación Bancaria).
 *
 * Operaciones de cartera y rechazo con reversión financiera trazable.
 */
class ChequeController extends Controller
{
    public function __construct(private ChequeService $service) {}

    public function index(Request $request)
    {
        $estado = $request->query('estado');

        return Response::json($this->service->listar($estado));
    }

    public function show(int $id)
    {
        $cheque = $this->service->buscar($id);

        if ($cheque === null) {
            return Response::error('Cheque no encontrado', 404);
        }

        return Response::json($cheque);
    }

    public function depositar(int $id)
    {
        $resultado = $this->service->depositar($id);

        if ($resultado === null) {
            return Response::error('Cheque no encontrado', 404);
        }

        if ($resultado['error'] ?? false) {
            return Response::error($resultado['mensaje'], 400);
        }

        return Response::json($resultado);
    }

    public function rechazar(Request $request, int $id)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:500']);
        $resultado = $this->service->rechazar($id, $data['motivo']);

        if ($resultado === null) {
            return Response::error('Cheque no encontrado', 404);
        }

        if ($resultado['error'] ?? false) {
            return Response::error($resultado['mensaje'], 400);
        }

        return Response::json($resultado);
    }
}
