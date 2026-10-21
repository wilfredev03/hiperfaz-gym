<?php

namespace App\Http\Controllers;

use App\Http\Requests\SocioRequest;
use App\Models\Asistencia;
use App\Models\Cliente;
use App\Models\Suscripcion;
use App\Services\SuscripcionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocioController extends Controller
{
    private const FILTROS = ['todos', 'vigentes', 'por_vencer', 'vencidas', 'sin_membresia', 'con_deuda'];

    /**
     * Lista de socios (`clientes`) con la situación de su membresía. Los
     * filtros de vencimiento salen de la misma subconsulta que arma la
     * columna `vence_el`, así que lista y filtro nunca discrepan.
     */
    public function index(Request $request, SuscripcionService $suscripciones): Response
    {
        $busqueda = trim((string) $request->query('busqueda', ''));
        $filtro = in_array($request->query('filtro'), self::FILTROS, true) ? $request->query('filtro') : 'todos';

        $hoy = now()->toDateString();
        $limite = now()->addDays(SuscripcionService::DIAS_POR_VENCER)->toDateString();
        $vence = SuscripcionService::VENCE_EL_SQL;

        $socios = Cliente::query()
            ->select('clientes.*')
            ->selectRaw("$vence as vence_el")
            ->when($busqueda !== '', fn ($query) => $query->where(function ($query) use ($busqueda) {
                $query->where('nombre', 'ilike', "%{$busqueda}%")
                    ->orWhere('documento', 'ilike', "%{$busqueda}%");
            }))
            ->when($filtro === 'vigentes', fn ($query) => $query->whereRaw("$vence >= ?", [$hoy]))
            ->when($filtro === 'por_vencer', fn ($query) => $query->whereRaw("$vence between ? and ?", [$hoy, $limite]))
            ->when($filtro === 'vencidas', fn ($query) => $query->whereRaw("$vence < ?", [$hoy]))
            ->when($filtro === 'sin_membresia', fn ($query) => $query->whereRaw("$vence is null"))
            ->when($filtro === 'con_deuda', fn ($query) => $query->where('saldo_pendiente', '>', 0))
            // los que vencen antes primero: es lo que recepción necesita ver
            ->when(in_array($filtro, ['por_vencer', 'vencidas'], true), fn ($query) => $query->orderByRaw("$vence"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Cliente $socio) => [
                'id' => $socio->id,
                'nombre' => $socio->nombre,
                'documento' => $socio->documento,
                'telefono' => $socio->telefono,
                'saldo_pendiente' => $socio->saldo_pendiente,
                'activo' => $socio->activo,
                'vence_el' => $socio->vence_el,
                ...$suscripciones->vigenciaDe($socio->vence_el),
            ]);

        return Inertia::render('socios/Index', [
            'socios' => $socios,
            'busqueda' => $busqueda,
            'filtro' => $filtro,
            'resumen' => [
                'vigentes' => Cliente::whereRaw("$vence >= ?", [$hoy])->count(),
                'por_vencer' => Cliente::whereRaw("$vence between ? and ?", [$hoy, $limite])->count(),
                'vencidas' => Cliente::whereRaw("$vence < ?", [$hoy])->count(),
            ],
        ]);
    }

    public function show(Cliente $socio, SuscripcionService $suscripciones): Response
    {
        $venceEl = Suscripcion::where('cliente_id', $socio->id)->whereIn('estado', ['activa', 'vencida'])->max('fecha_vencimiento');

        return Inertia::render('socios/Show', [
            'socio' => $socio->only([
                'id', 'nombre', 'documento', 'telefono', 'email', 'direccion', 'fecha_nacimiento',
                'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'id_acceso',
                'saldo_pendiente', 'activo',
            ]),
            'membresia' => [
                'vence_el' => $venceEl ? substr((string) $venceEl, 0, 10) : null,
                ...$suscripciones->vigenciaDe($venceEl),
            ],
            'suscripciones' => $socio->suscripciones()
                ->with(['plan.producto:id,nombre', 'ventaDetalle.venta:id,numero_venta'])
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (Suscripcion $suscripcion) => [
                    'id' => $suscripcion->id,
                    'plan' => $suscripcion->plan->producto->nombre,
                    'fecha_inicio' => $suscripcion->fecha_inicio->toDateString(),
                    'fecha_vencimiento' => $suscripcion->fecha_vencimiento->toDateString(),
                    'estado' => $suscripcion->estado,
                    'numero_venta' => $suscripcion->ventaDetalle->venta->numero_venta,
                ]),
            'asistencias' => $socio->asistencias()
                ->orderByDesc('fecha_hora')
                ->limit(20)
                ->get(['id', 'fecha_hora', 'resultado', 'origen']),
        ]);
    }

    public function store(SocioRequest $request): RedirectResponse
    {
        $socio = Cliente::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente creado.')]);

        return to_route('socios.show', $socio);
    }

    public function update(SocioRequest $request, Cliente $socio): RedirectResponse
    {
        $socio->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente actualizado.')]);

        return back();
    }
}
