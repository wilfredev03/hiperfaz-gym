<?php

namespace App\Services\Recordatorios;

use App\Models\Recordatorio;
use App\Models\RecordatorioAjuste;
use App\Models\RecordatorioPlantilla;
use App\Models\Suscripcion;
use App\Services\SuscripcionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Decide a quién se le avisa y deja los avisos en la cola como `pendiente`.
 * No envía nada: eso es del `MotorEnvio`, que además respeta el horario, las
 * esperas y los descansos.
 */
class RecordatorioService
{
    public function __construct(private GeneradorMensaje $generador) {}

    /**
     * Propone los avisos de hoy: a quien le vencen `dias_aviso` días adelante
     * (`por_vencer`) y a quien le vence hoy (`vence_hoy`). Es idempotente:
     * una suscripción nunca recibe dos veces el mismo tipo de aviso.
     *
     * @return array{creados: int, sin_telefono: int, sin_plantilla: int, ya_propuestos: int}
     */
    public function proponer(): array
    {
        $ajuste = RecordatorioAjuste::actual();
        $hoy = CarbonImmutable::today();
        $resultado = ['creados' => 0, 'sin_telefono' => 0, 'sin_plantilla' => 0, 'ya_propuestos' => 0];

        $tipos = [
            'por_vencer' => $hoy->addDays($ajuste->dias_aviso),
            'vence_hoy' => $hoy,
        ];

        foreach ($tipos as $tipo => $fechaVencimiento) {
            $plantillas = RecordatorioPlantilla::where('tipo', $tipo)->where('activa', true)->get();
            $ultima = null;

            foreach ($this->candidatas($fechaVencimiento, $ajuste->duracion_minima_dias) as $suscripcion) {
                $telefono = TelefonoVenezolano::normalizar($suscripcion->cliente->telefono);

                if (! $telefono) {
                    $resultado['sin_telefono']++;

                    continue;
                }

                if ($plantillas->isEmpty()) {
                    $resultado['sin_plantilla']++;

                    continue;
                }

                if (Recordatorio::where('suscripcion_id', $suscripcion->id)->where('tipo', $tipo)->exists()) {
                    $resultado['ya_propuestos']++;

                    continue;
                }

                $plantilla = $this->elegirPlantilla($plantillas, $ultima);
                $ultima = $plantilla->id;
                $variables = $this->variables($suscripcion, $hoy);

                try {
                    Recordatorio::create([
                        'cliente_id' => $suscripcion->cliente_id,
                        'suscripcion_id' => $suscripcion->id,
                        'plantilla_id' => $plantilla->id,
                        'tipo' => $tipo,
                        'telefono' => $telefono,
                        'mensaje' => $this->generador->generar($plantilla, $variables),
                        'variables' => $variables,
                        'estado' => 'pendiente',
                        'fecha_aviso' => $hoy->toDateString(),
                    ]);
                    $resultado['creados']++;
                } catch (UniqueConstraintViolationException) {
                    // otra corrida lo propuso entre la consulta y el insert
                    $resultado['ya_propuestos']++;
                }
            }
        }

        return $resultado;
    }

    /**
     * Suscripciones activas que vencen ese día, de clientes activos para los que
     * esa es su última membresía (quien ya renovó no recibe aviso), y de planes
     * con la duración mínima (un pase de día no se "recuerda").
     *
     * @return Collection<int, Suscripcion>
     */
    private function candidatas(CarbonImmutable $fechaVencimiento, int $duracionMinimaDias): Collection
    {
        return Suscripcion::query()
            ->with(['cliente', 'plan.producto'])
            ->where('estado', 'activa')
            ->whereDate('fecha_vencimiento', $fechaVencimiento->toDateString())
            ->whereHas('cliente', fn ($cliente) => $cliente
                ->where('activo', true)
                ->whereRaw(SuscripcionService::VENCE_EL_SQL.' = ?', [$fechaVencimiento->toDateString()]))
            ->get()
            ->filter(fn (Suscripcion $s) => $s->fecha_inicio->diffInDays($s->fecha_vencimiento) + 1 >= $duracionMinimaDias)
            ->values();
    }

    /** Elige al azar una plantilla distinta de la última usada, si hay más de una. */
    private function elegirPlantilla(Collection $plantillas, ?int $ultimaId): RecordatorioPlantilla
    {
        $opciones = $plantillas->count() > 1 ? $plantillas->reject(fn ($p) => $p->id === $ultimaId) : $plantillas;

        return $opciones->random();
    }

    /** @return array<string, string> */
    private function variables(Suscripcion $suscripcion, CarbonImmutable $hoy): array
    {
        $dias = (int) $hoy->diffInDays($suscripcion->fecha_vencimiento, false);

        return [
            'nombre' => GeneradorMensaje::primerNombre($suscripcion->cliente->nombre),
            'nombre_completo' => $suscripcion->cliente->nombre,
            'plan' => $suscripcion->plan->producto->nombre,
            'dias' => GeneradorMensaje::textoDias($dias),
            'fecha' => $suscripcion->fecha_vencimiento->format('d/m/Y'),
        ];
    }
}
