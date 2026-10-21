<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Cliente;
use App\Services\SuscripcionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AsistenciaController extends Controller
{
    /**
     * Recepción: busca al socio, registra su entrada y ve las de hoy. Los
     * candidatos solo se consultan al buscar (recarga parcial por `busqueda`).
     */
    public function index(Request $request, SuscripcionService $suscripciones): Response
    {
        $busqueda = trim((string) $request->query('busqueda', ''));
        $hoy = now()->toDateString();

        $deHoy = Asistencia::query()->whereDate('fecha_hora', $hoy);

        return Inertia::render('asistencias/Index', [
            'busqueda' => $busqueda,
            'candidatos' => $busqueda === '' ? [] : Cliente::query()
                ->select('clientes.id', 'clientes.nombre', 'clientes.documento', 'clientes.activo')
                ->selectRaw(SuscripcionService::VENCE_EL_SQL.' as vence_el')
                ->where(fn ($query) => $query
                    ->where('nombre', 'ilike', "%{$busqueda}%")
                    ->orWhere('documento', 'ilike', "%{$busqueda}%")
                    ->orWhere('id_acceso', $busqueda))
                ->orderBy('nombre')
                ->limit(8)
                ->get()
                ->map(fn (Cliente $socio) => [
                    'id' => $socio->id,
                    'nombre' => $socio->nombre,
                    'documento' => $socio->documento,
                    'activo' => $socio->activo,
                    'vence_el' => $socio->vence_el,
                    ...$suscripciones->vigenciaDe($socio->vence_el),
                ]),
            'resumen' => [
                'permitidas' => (clone $deHoy)->where('resultado', 'permitido')->count(),
                'denegadas' => (clone $deHoy)->where('resultado', 'denegado')->count(),
            ],
            'asistencias' => (clone $deHoy)
                ->with('cliente:id,nombre,documento')
                ->orderByDesc('fecha_hora')
                ->limit(100)
                ->get(['id', 'cliente_id', 'fecha_hora', 'resultado', 'origen']),
        ]);
    }

    /**
     * Registro manual de una entrada. El sistema decide si el socio tiene
     * derecho (principio #11): suscripción vigente y socio activo. Una
     * entrada denegada también queda registrada.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['cliente_id' => ['required', 'exists:clientes,id']]);

        $socio = Cliente::findOrFail($data['cliente_id']);
        $permitido = $socio->activo && $socio->suscripcionVigente() !== null;

        Asistencia::create([
            'cliente_id' => $socio->id,
            'fecha_hora' => now(),
            'resultado' => $permitido ? 'permitido' : 'denegado',
            'origen' => 'manual',
            'usuario_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', $permitido
            ? ['type' => 'success', 'message' => __('Entrada registrada: :nombre.', ['nombre' => $socio->nombre])]
            : ['type' => 'error', 'message' => __('Acceso denegado: :nombre no tiene una membresía vigente.', ['nombre' => $socio->nombre])]);

        return back();
    }
}
