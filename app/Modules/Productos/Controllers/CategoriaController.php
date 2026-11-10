<?php
namespace App\Modules\Productos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Categoria;
use App\Modules\Productos\Services\CategoryHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriaController extends Controller
{
    public function index()
    {
        $records = DB::table('categoria')->get()->keyBy('id_categoria')->all();
        $rows = Categoria::withCount('productos as total_productos')->orderBy('nombre')->get();
        foreach ($rows as $row) {
            $row->setAttribute('ruta', app(CategoryHierarchy::class)->path($row->id_categoria, $records));
        }
        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function show(int $id)
    {
        return response()->json(['status' => 'success', 'data' => Categoria::withCount('productos as total_productos')->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id = null)
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);
        $rules = ['nombre' => ['required','string','max:100', \Illuminate\Validation\Rule::unique('categoria','nombre')->ignore($id,'id_categoria')],
            'id_categoria_padre' => 'nullable|integer|min:1'];
        $validator = validator($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }
        $row = DB::transaction(function () use ($request, $id) {
            DB::table('categoria')->orderBy('id_categoria')->lockForUpdate()->get();
            $row = $id === null ? new Categoria : Categoria::findOrFail($id);
            $parent = $request->exists('id_categoria_padre') ? ($request->filled('id_categoria_padre') ? (int) $request->input('id_categoria_padre') : null) : $row->id_categoria_padre;
            app(CategoryHierarchy::class)->requireParent($id, $parent);
            $row->nombre = $request->input('nombre');
            $row->id_categoria_padre = $parent;
            $row->fecha_creacion ??= today();
            $row->fecha_modificacion = today();
            $row->save();
            return $row;
        }, 3);
        return response()->json(['status'=>'success', 'message'=>$id === null ? 'Categoría creada exitosamente.' : 'Categoría actualizada exitosamente.', 'data'=>$row], $id === null ? 201 : 200);
    }

    public function destroy(int $id)
    {
        return DB::transaction(function () use ($id) {
            DB::table('categoria')->orderBy('id_categoria')->lockForUpdate()->get();
            $row = Categoria::findOrFail($id);
            if ($row->productos()->exists() || Categoria::where('id_categoria_padre', $id)->exists()) {
                return response()->json(['status'=>'error','message'=>'No se puede eliminar la categoría porque tiene productos o subcategorías.'],400);
            }
            $row->delete();
            return response()->json(['status'=>'success','message'=>'Categoría eliminada exitosamente.']);
        }, 3);
    }
}
