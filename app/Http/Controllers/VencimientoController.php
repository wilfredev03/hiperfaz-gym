<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VencimientoController extends Controller
{
    private const VISTAS = ['por_vencer', 'vencidos'];

    /** Hasta cuántos días atrás se siguen mostrando los socios que no renovaron. */
    private const DIAS_VENCIDOS_RECIENTES = 30;

    /**
     * Seguimiento de renovaciones para recepción: los socios activos a los
     * que les vence la membresía en los próximos días (`por_vencer`) y los que
     * se les venció hace poco y no han renovado (`vencidos`). Ambas listas
     * salen del último día de acceso del socio (`VENCE_EL_SQL`), así que quien
     * ya renovó deja de aparecer solo.
     */
    public function index(Request $request, SuscripcionService $suscripciones): Response
    {
        $vista = in_array($request->query('vista'), self::VISTAS, true) ? $request->query('vista') : 'por_vencer';

        $vence = SuscripcionService::VENCE_EL_SQL;
        $hoy = now()->toDateString();
        $limite = now()->addDays(SuscripcionService::DIAS_POR_VENCER)->toDateString();
        $desde = now()->subDays(self::DIAS_VENCIDOS_RECIENTES)->toDateString();
        $ayer = now()->subDay()->toDateString();

        $porVencer = fn () => Cliente::query()->where('activo', true)->whereRaw("$vence between ? and ?", [$hoy, $limite]);
        $vencidos = fn () => Cliente::query()->where('activo', true)->whereRaw("$vence between ? and ?", [$desde, $ayer]);

        $socios = ($vista === 'por_vencer' ? $porVencer() : $vencidos())
            ->select('clientes.*')
            ->selectRaw("$vence as vence_el")
            // el que vence primero (o se venció primero) va arriba
            ->orderByRaw($vista === 'por_vencer' ? "$vence asc" : "$vence desc")
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Cliente $socio) => [
                'id' => $socio->id,
                'nombre' => $socio->nombre,
                'telefono' => $socio->telefono,
                'saldo_pendiente' => $socio->saldo_pendiente,
                'vence_el' => substr((string) $socio->vence_el, 0, 10),
                ...$suscripciones->vigenciaDe($socio->vence_el),
                'dias_vencida' => $vista === 'vencidos'
                    ? (int) Carbon::parse($socio->vence_el)->startOfDay()->diffInDays(now()->startOfDay(), true)
                    : null,
            ]);

        return Inertia::render('vencimientos/Index', [
            'socios' => $socios,
            'vista' => $vista,
            'resumen' => [
                'por_vencer' => $porVencer()->count(),
                'vencidos' => $vencidos()->count(),
                'dias_por_vencer' => SuscripcionService::DIAS_POR_VENCER,
                'dias_vencidos' => self::DIAS_VENCIDOS_RECIENTES,
            ],
        ]);
    }
}
