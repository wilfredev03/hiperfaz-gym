<?php

namespace App\Http\Controllers;

use App\Http\Requests\SucursalRequest;
use App\Models\Categoria;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SucursalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('settings/sucursales/Index', [
            'sucursales' => Sucursal::orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('settings/sucursales/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SucursalRequest $request): RedirectResponse
    {
        Sucursal::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sucursal creada.')]);

        return to_route('sucursales.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Sucursal $sucursal): Response
    {
        return $this->edit($sucursal);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sucursal $sucursal): Response
    {
        return Inertia::render('settings/sucursales/Edit', [
            'sucursal' => $sucursal,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SucursalRequest $request, Sucursal $sucursal): RedirectResponse
    {
        $sucursal->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sucursal actualizada.')]);

        return to_route('sucursales.index');
    }

    /**
     * Remove the specified resource from storage. Siempre debe quedar al
     * menos una sucursal, porque el resto del sistema (ventas, cierres de
     * caja) asume que hay una sucursal activa para operar.
     */
    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        if (Sucursal::count() <= 1) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se puede eliminar: debe quedar al menos una sucursal.')]);

            return back();
        }

        $sucursal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sucursal eliminada.')]);

        return to_route('sucursales.index');
    }
}
