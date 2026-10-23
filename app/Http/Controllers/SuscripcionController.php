<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuscripcionController extends Controller
{
    /**
     * Corrige las fechas de una membresía cargada con error (por ejemplo, un
     * día mal puesto). Lo puede hacer cualquier usuario autenticado; queda el
     * motivo y quién lo hizo. No cambia la venta ni el dinero cobrado.
     */
    public function corregir(Request $request, Suscripcion $suscripcion): RedirectResponse
    {
        $data = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $suscripcion->corregirFechas(
            $data['fecha_inicio'],
            $data['fecha_vencimiento'],
            $data['motivo'],
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fechas de la membresía corregidas.')]);

        return back();
    }
}
