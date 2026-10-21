<?php

namespace App\Http\Controllers;

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Http\Requests\TasaCambioRequest;
use App\Models\TasaCambio;
use App\Services\TasaBcv\TasaBcvProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TasaCambioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('tasas_cambio/Index', [
            'tasas' => TasaCambio::with('usuario')->orderByDesc('fecha')->orderByDesc('id')->paginate(15),
            'tasaActual' => TasaCambio::actual()?->load('usuario'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TasaCambioRequest $request): RedirectResponse
    {
        TasaCambio::registrarNueva(
            tasa: $request->validated('tasa'),
            tipo: 'manual',
            usuarioId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tasa de cambio registrada.')]);

        // el modal para registrar tasa se reutiliza desde otras pantallas
        // (ej. Nueva venta), así que vuelve a donde estaba el usuario en vez
        // de forzar siempre la navegación a Tasas de cambio
        return back(fallback: route('tasas_cambio.index'));
    }

    /**
     * Reintento manual de la carga automática (la misma que corre programada
     * cada mañana, ver ObtenerTasaBcvCommand). A diferencia del comando, sí
     * reemplaza una tasa manual de hoy: aquí alguien lo pidió explícitamente.
     */
    public function cargarAutomatica(Request $request, TasaBcvProvider $proveedor): RedirectResponse
    {
        if (TasaCambio::vigenteHoy()?->tipo === 'automatica') {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('La tasa BCV de hoy ya se cargó automáticamente.')]);

            return back(fallback: route('tasas_cambio.index'));
        }

        try {
            $tasa = $proveedor->obtener();
        } catch (TasaBcvNoDisponibleException $e) {
            Log::error('Falló el reintento manual de la tasa BCV automática.', [
                'error' => $e->getMessage(),
                'usuario_id' => $request->user()->id,
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se pudo obtener la tasa del BCV. Intenta más tarde o regístrala manualmente.')]);

            return back(fallback: route('tasas_cambio.index'));
        }

        TasaCambio::registrarNueva(
            tasa: $tasa,
            tipo: 'automatica',
            usuarioId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tasa BCV cargada automáticamente.')]);

        return back(fallback: route('tasas_cambio.index'));
    }
}
