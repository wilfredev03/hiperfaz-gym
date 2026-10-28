<?php

namespace App\Services\Recordatorios;

use App\Models\Recordatorio;
use App\Models\RecordatorioAjuste;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;

/**
 * Manda a lo sumo UN recordatorio por llamada, y solo si toca: el interruptor
 * está activo, estamos dentro del horario, no hay descanso ni espera en curso
 * y el bot está conectado. El comando `recordatorios:enviar` lo llama cada
 * minuto; la espera aleatoria entre mensajes (1 a 3 min) y los descansos
 * (10 a 15 min cada 8 a 12 mensajes) viven en `recordatorio_ajustes`, así que
 * reiniciar el servidor no los reinicia. Detener = poner `activo` en false.
 */
class MotorEnvio
{
    /** Veces que se intenta un mensaje antes de darlo por fallido. */
    public const MAX_INTENTOS = 3;

    public function __construct(private WhatsAppCliente $bot) {}

    /**
     * @return string inactivo | fuera_de_horario | descanso | espera | bot_desconectado | sin_pendientes | enviado | fallido
     */
    public function tick(): string
    {
        $this->rescatarInterrumpidos();

        $ajuste = RecordatorioAjuste::actual();

        if (! $ajuste->activo) {
            return 'inactivo';
        }

        if (! $ajuste->dentroDeHorario()) {
            return 'fuera_de_horario';
        }

        if ($ajuste->descanso_hasta?->isFuture()) {
            return 'descanso';
        }

        if ($ajuste->proximo_envio_at?->isFuture()) {
            return 'espera';
        }

        if (! $this->bot->estado()['conectado']) {
            return 'bot_desconectado';
        }

        $recordatorio = $this->reclamar();

        return $recordatorio ? $this->enviar($recordatorio) : 'sin_pendientes';
    }

    /**
     * Un mensaje que lleva rato en `enviando` es de un proceso que murió a
     * medio envío. No se reintenta solo (podría haber salido y duplicarse):
     * queda `fallido` para que el dueño decida si lo reenvía.
     */
    private function rescatarInterrumpidos(): void
    {
        Recordatorio::query()
            ->where('estado', 'enviando')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'estado' => 'fallido',
                'detalle' => 'El envío se interrumpió; no se sabe si el mensaje salió.',
            ]);
    }

    /**
     * Toma el siguiente pendiente y lo marca `enviando` dentro de una
     * transacción que bloquea la fila de ajustes, para que dos ejecuciones
     * simultáneas nunca manden el mismo mensaje. Reserva además la espera
     * máxima: si el proceso muere a mitad del envío, el siguiente no sale
     * enseguida. Los pendientes que ya no corresponden se cancelan en el camino.
     */
    private function reclamar(): ?Recordatorio
    {
        return DB::transaction(function () {
            $ajuste = RecordatorioAjuste::query()->lockForUpdate()->firstOrFail();

            if (! $ajuste->activo || $ajuste->proximo_envio_at?->isFuture() || $ajuste->descanso_hasta?->isFuture()) {
                return null;
            }

            for ($i = 0; $i < 50; $i++) {
                $recordatorio = Recordatorio::query()
                    ->where('estado', 'pendiente')
                    ->orderByRaw("tipo = 'vence_hoy' desc")
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $recordatorio) {
                    return null;
                }

                if ($motivo = $this->motivoObsoleto($recordatorio)) {
                    $recordatorio->update(['estado' => 'cancelado', 'detalle' => $motivo]);

                    continue;
                }

                $recordatorio->update(['estado' => 'enviando', 'intentos' => $recordatorio->intentos + 1]);
                $ajuste->update(['proximo_envio_at' => now()->addSeconds($ajuste->espera_max_seg)]);

                return $recordatorio;
            }

            return null;
        });
    }

    /** Por qué un pendiente ya no debe enviarse (null si sigue vigente). */
    private function motivoObsoleto(Recordatorio $recordatorio): ?string
    {
        if ($recordatorio->fecha_aviso->isBefore(today())) {
            return 'No se envió el día programado.';
        }

        $suscripcion = $recordatorio->suscripcion;

        if ($suscripcion->estado === 'cancelada') {
            return 'La membresía fue cancelada.';
        }

        $renovo = Suscripcion::query()
            ->where('cliente_id', $recordatorio->cliente_id)
            ->whereIn('estado', ['activa', 'vencida'])
            ->whereDate('fecha_vencimiento', '>', $suscripcion->fecha_vencimiento->toDateString())
            ->exists();

        return $renovo ? 'El cliente ya renovó.' : null;
    }

    private function enviar(Recordatorio $recordatorio): string
    {
        try {
            $respuesta = $this->bot->enviar($recordatorio->telefono, $recordatorio->mensaje);

            $recordatorio->update([
                'estado' => 'enviado',
                'enviado_en' => now(),
                'id_mensaje_whatsapp' => $respuesta['id'],
                'detalle' => null,
            ]);
            $this->programarSiguiente(exito: true);

            return 'enviado';
        } catch (WhatsAppException $e) {
            $terminal = $e->definitivo || $recordatorio->intentos >= self::MAX_INTENTOS;

            $recordatorio->update(['estado' => $terminal ? 'fallido' : 'pendiente', 'detalle' => $e->getMessage()]);
            $this->programarSiguiente(exito: false);

            return 'fallido';
        }
    }

    /**
     * Fija cuándo puede salir el próximo mensaje: una espera aleatoria, o un
     * descanso largo si ya se mandaron los que tocaban. Un envío fallido no
     * cuenta para el descanso, pero igual espera para no insistir de golpe.
     */
    private function programarSiguiente(bool $exito): void
    {
        $ajuste = RecordatorioAjuste::actual();
        $enviados = $ajuste->enviados_desde_descanso + ($exito ? 1 : 0);
        $meta = $ajuste->mensajes_para_descanso
            ?? random_int($ajuste->mensajes_descanso_min, $ajuste->mensajes_descanso_max);

        if ($exito && $enviados >= $meta) {
            $descansoHasta = now()->addMinutes(random_int($ajuste->descanso_min, $ajuste->descanso_max));

            $ajuste->update([
                'enviados_desde_descanso' => 0,
                'mensajes_para_descanso' => random_int($ajuste->mensajes_descanso_min, $ajuste->mensajes_descanso_max),
                'descanso_hasta' => $descansoHasta,
                'proximo_envio_at' => $descansoHasta,
            ]);

            return;
        }

        $ajuste->update([
            'enviados_desde_descanso' => $enviados,
            'mensajes_para_descanso' => $meta,
            'proximo_envio_at' => now()->addSeconds(random_int($ajuste->espera_min_seg, $ajuste->espera_max_seg)),
        ]);
    }
}
