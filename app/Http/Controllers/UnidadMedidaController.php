<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnidadMedidaRequest;
use App\Models\UnidadMedida;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnidadMedidaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('settings/unidades_medida/Index', [
            'unidadesMedida' => UnidadMedida::withCount('productos')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UnidadMedidaRequest $request): RedirectResponse
    {
        UnidadMedida::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida creada.')]);

        return to_route('unidades_medida.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UnidadMedidaRequest $request, UnidadMedida $unidad_medida): RedirectResponse
    {
        $unidad_medida->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida actualizada.')]);

        return to_route('unidades_medida.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UnidadMedida $unidad_medida): RedirectResponse
    {
        try {
            DB::transaction(fn () => $unidad_medida->delete());
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se puede eliminar: tiene productos asociados.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Unidad de medida eliminada.')]);

        return to_route('unidades_medida.index');
    }
}
