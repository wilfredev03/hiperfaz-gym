<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetodoPagoRequest;
use App\Models\MetodoPago;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MetodoPagoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('settings/metodos_pago/Index', [
            'metodosPago' => MetodoPago::orderBy('orden')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MetodoPagoRequest $request): RedirectResponse
    {
        MetodoPago::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Método de pago creado.')]);

        return to_route('metodos_pago.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MetodoPagoRequest $request, MetodoPago $metodo_pago): RedirectResponse
    {
        $metodo_pago->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Método de pago actualizado.')]);

        return to_route('metodos_pago.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MetodoPago $metodo_pago): RedirectResponse
    {
        try {
            DB::transaction(fn () => $metodo_pago->delete());
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se puede eliminar: tiene pagos o cierres de caja asociados.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Método de pago eliminado.')]);

        return to_route('metodos_pago.index');
    }
}
