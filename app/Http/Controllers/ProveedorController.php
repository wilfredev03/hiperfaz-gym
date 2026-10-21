<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('settings/proveedores/Index', [
            'proveedores' => Proveedor::withCount('ordenesCompra')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProveedorRequest $request): RedirectResponse
    {
        Proveedor::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proveedor creado.')]);

        return to_route('proveedores.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proveedor actualizado.')]);

        return to_route('proveedores.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        try {
            DB::transaction(fn () => $proveedor->delete());
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se puede eliminar: tiene órdenes de compra asociadas.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proveedor eliminado.')]);

        return to_route('proveedores.index');
    }
}
