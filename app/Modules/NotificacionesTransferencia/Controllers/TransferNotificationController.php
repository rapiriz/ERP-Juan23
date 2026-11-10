<?php

namespace App\Modules\NotificacionesTransferencia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\NotificacionesTransferencia\Models\TransferNotification;
use App\Modules\NotificacionesTransferencia\Requests\StoreTransferNotificationRequest;
use App\Modules\NotificacionesTransferencia\Services\TransferNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TransferNotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['estado' => ['nullable', 'in:pendiente,validada,rechazada,todas'], 'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        $state = $filters['estado'] ?? 'pendiente';
        $notifications = TransferNotification::with('cliente')->when($state !== 'todas', fn ($q) => $q->where('estado', $state))
            ->when(isset($filters['cliente_id']), fn ($q) => $q->where('cliente_id', $filters['cliente_id']))->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->withQueryString();
        if ($request->expectsJson()) {
            return response()->json(['data' => $notifications->getCollection()->map(fn ($n) => $this->resource($n))->all(),
                'meta' => ['current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage(), 'total' => $notifications->total()]])->header('Cache-Control', 'no-store');
        }

        return response()->view('transferencias.index', [
            'notifications' => $notifications,
            'clientes' => Cliente::orderBy('apellido_razon_social')->get(), 'estado' => $state, 'clienteId' => $filters['cliente_id'] ?? '',
        ])->header('Cache-Control', 'no-store');
    }

    public function store(StoreTransferNotificationRequest $request, TransferNotificationService $notifications)
    {
        $notification = $notifications->register($request->validated(), $request->file('comprobante'), $request->user());
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->resource($notification)], $notification->wasRecentlyCreated ? 201 : 200)->header('Cache-Control', 'no-store');
        }

        return redirect()->route('transferencias.index', ['cliente_id' => $notification->cliente_id])->with('success', 'Notificación registrada correctamente. Queda pendiente de validación y no acredita el pago.');
    }

    public function show(TransferNotification $transferencia)
    {
        return response()->json(['data' => $this->resource($transferencia)])->header('Cache-Control', 'no-store');
    }

    public function download(TransferNotification $transferencia)
    {
        abort_unless(Storage::disk('local')->exists($transferencia->archivo), 404, 'El comprobante ya no está disponible.');

        return Storage::disk('local')->download($transferencia->archivo, 'comprobante-'.$transferencia->id.'.'.pathinfo($transferencia->archivo, PATHINFO_EXTENSION),
            ['Content-Type' => $transferencia->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }

    public function review(Request $request, TransferNotification $transferencia, TransferNotificationService $notifications)
    {
        $data = $request->validate(['estado' => ['required', 'in:validada,rechazada'], 'nota' => ['required', 'string', 'min:3', 'max:1000']]);
        $notifications->review($transferencia, $request->user(), $data);
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->resource($transferencia->fresh())])->header('Cache-Control', 'no-store');
        }

        return back()->with('success', 'Revisión registrada. Esta acción no acredita el pago automáticamente.');
    }

    private function resource(TransferNotification $notification): array
    {
        return ['id' => $notification->id, 'cliente_id' => $notification->cliente_id, 'fecha' => $notification->fecha->toDateString(),
            'monto' => $notification->monto, 'referencia' => $notification->referencia, 'estado' => $notification->estado,
            'comprobante_url' => url('/api/v1/transferencias/'.$notification->id.'/comprobante'),
            'registrado_por' => $notification->registrado_por, 'revisado_por' => $notification->revisado_por,
            'nota_revision' => $notification->nota_revision];
    }
}
