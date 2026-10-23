<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembresiaRequest;
use App\Http\Requests\SocioRequest;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\SuscripcionCorreccion;
use App\Models\TasaCambio;
use App\Services\MembresiaService;
use App\Services\SuscripcionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    /** Alta de cliente en página propia: datos y, si se quiere, su membresía. */
    public function create(): Response
    {
        return Inertia::render('socios/Create', $this->opcionesDeMembresia());
    }

    /**
     * Lo que necesita la interfaz para activar una membresía: los planes que
     * se pueden vender, los métodos de pago y la tasa de hoy (null si nadie
     * la cargó: entonces no se puede cobrar).
     *
     * @return array<string, mixed>
     */
    private function opcionesDeMembresia(): array
    {
        return [
            'planes' => Plan::query()
                ->join('productos', 'productos.id', '=', 'planes.producto_id')
                ->where('productos.activo', true)
                ->where('planes.personalizado', false)
                ->orderBy('planes.duracion_dias')
                ->orderBy('productos.nombre')
                ->get(['planes.id', 'productos.nombre', 'productos.precio_venta', 'planes.duracion_dias']),
            'metodosPago' => MetodoPago::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'moneda', 'requiere_referencia']),
            'tasaCambio' => TasaCambio::vigenteHoy()?->only(['id', 'tasa', 'fecha']),
        ];
    }

    public function show(Cliente $socio, SuscripcionService $suscripciones): Response
    {
        $venceEl = Suscripcion::where('cliente_id', $socio->id)->whereIn('estado', ['activa', 'vencida'])->max('fecha_vencimiento');

        return Inertia::render('socios/Show', [
            'socio' => $socio->only([
                'id', 'nombre', 'documento', 'telefono', 'email', 'direccion',
                'id_acceso', 'saldo_pendiente', 'activo',
            ]),
            'membresia' => [
                'vence_el' => $venceEl ? substr((string) $venceEl, 0, 10) : null,
                ...$suscripciones->vigenciaDe($venceEl),
            ],
            'suscripciones' => $socio->suscripciones()
                ->with(['plan.producto:id,nombre', 'ventaDetalle.venta:id,numero_venta'])
                ->withCount('correcciones')
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
                    'corregida' => $suscripcion->correcciones_count > 0,
                ]),
            'correcciones' => SuscripcionCorreccion::query()
                ->whereIn('suscripcion_id', $socio->suscripciones()->select('id'))
                ->with(['usuario:id,name', 'suscripcion.plan.producto:id,nombre'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (SuscripcionCorreccion $correccion) => [
                    'id' => $correccion->id,
                    'plan' => $correccion->suscripcion->plan->producto->nombre,
                    'usuario' => $correccion->usuario->name,
                    'motivo' => $correccion->motivo,
                    'anterior' => [$correccion->fecha_inicio_anterior->toDateString(), $correccion->fecha_vencimiento_anterior->toDateString()],
                    'nueva' => [$correccion->fecha_inicio_nueva->toDateString(), $correccion->fecha_vencimiento_nueva->toDateString()],
                    'fecha' => $correccion->created_at->toIso8601String(),
                ]),
            'asistencias' => $socio->asistencias()
                ->orderByDesc('fecha_hora')
                ->limit(20)
                ->get(['id', 'fecha_hora', 'resultado', 'origen']),
            ...$this->opcionesDeMembresia(),
        ]);
    }

    /**
     * Crea el cliente y, si en el mismo formulario se eligió un plan, le
     * activa la membresía: todo o nada, para no dejar un cliente a medias si
     * el cobro falla (por ejemplo, sin tasa de hoy).
     */
    public function store(SocioRequest $request, MembresiaService $membresias): RedirectResponse
    {
        $datos = $request->safe()->except('membresia');
        $membresia = $request->validated('membresia');
        $conMembresia = ! empty($membresia['plan_id']) || ! empty($membresia['personalizada']);

        $socio = DB::transaction(function () use ($datos, $membresia, $conMembresia, $membresias) {
            $socio = Cliente::create($datos);

            if ($conMembresia) {
                $membresias->activar($socio, $membresia);
            }

            return $socio;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($conMembresia ? 'Cliente creado y membresía activada.' : 'Cliente creado.'),
        ]);

        return to_route('socios.show', $socio);
    }

    /** Activa o renueva la membresía del cliente (nueva suscripción, nunca se extiende la anterior). */
    public function membresia(MembresiaRequest $request, Cliente $socio, MembresiaService $membresias): RedirectResponse
    {
        if (! $socio->activo) {
            throw ValidationException::withMessages(['plan_id' => 'El cliente está inactivo: actívalo antes de venderle una membresía.']);
        }

        $venta = $membresias->activar($socio, $request->validated());
        $suscripcion = $membresias->suscripcionDe($venta);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Membresía activada hasta el :fecha.', ['fecha' => $suscripcion->fecha_vencimiento->format('d/m/Y')]),
        ]);

        return back();
    }

    public function update(SocioRequest $request, Cliente $socio): RedirectResponse
    {
        $socio->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente actualizado.')]);

        return back();
    }
}
