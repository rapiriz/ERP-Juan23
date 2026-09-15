<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Repositories\ProductoLoteRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductoLoteController extends Controller
{
    private ProductoLoteRepository $repository;

    public function __construct(ProductoLoteRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $filtros = $request->only(['producto', 'estado', 'ubicacion']);
        $pagina = $request->input('pagina', 1);

        $resultado = $this->repository->obtenerTodosPaginado($filtros, $pagina);

        return view('lotes.index', [
            'lotes' => $resultado['data'],
            'paginacion' => $resultado['paginacion'],
            'filtros' => $filtros
        ]);
    }

    public function create(): View
    {
        $productos = Producto::where('activo', true)->orderBy('nombre')->get();

        return view('lotes.create', compact('productos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'numero_lote' => 'required|string|unique:producto_lotes,numero_lote',
            'fecha_vencimiento' => 'required|date|after:today',
            'cantidad_inicial' => 'required|integer|min:1',
            'ubicacion_almacen' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string'
        ]);

        $validated['cantidad_actual'] = $validated['cantidad_inicial'];
        $validated['estado'] = 'activo';

        $this->repository->crear($validated);

        return redirect()
            ->route('lotes.index')
            ->with('exito', 'Lote creado correctamente.');
    }

    public function edit(int $id): View
    {
        $lote = $this->repository->obtenerPorId($id);

        if (!$lote) {
            abort(404);
        }

        $productos = Producto::where('activo', true)->orderBy('nombre')->get();

        return view('lotes.edit', compact('lote', 'productos'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'numero_lote' => 'required|string|unique:producto_lotes,numero_lote,' . $id,
            'fecha_vencimiento' => 'required|date',
            'cantidad_actual' => 'required|integer|min:0',
            'ubicacion_almacen' => 'nullable|string|max:255',
            'estado' => 'required|in:activo,vencido,proximos_a_vencer',
            'observaciones' => 'nullable|string'
        ]);

        $this->repository->actualizar($id, $validated);

        return redirect()
            ->route('lotes.index')
            ->with('exito', 'Lote actualizado correctamente.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $eliminado = $this->repository->eliminar($id);

        if (!$eliminado) {
            return redirect()
                ->route('lotes.index')
                ->with('error', 'No se pudo eliminar el lote.');
        }

        return redirect()
            ->route('lotes.index')
            ->with('exito', 'Lote eliminado correctamente.');
    }
}
