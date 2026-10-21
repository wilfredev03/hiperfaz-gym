<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaRequest;
use App\Models\Categoria;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('settings/categorias/Index', [
            'categorias' => Categoria::withCount('productos')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoriaRequest $request): RedirectResponse
    {
        Categoria::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoría creada.')]);

        return to_route('categorias.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoría actualizada.')]);

        return to_route('categorias.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        try {
            DB::transaction(fn () => $categoria->delete());
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se puede eliminar: tiene productos asociados.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoría eliminada.')]);

        return to_route('categorias.index');
    }
}
