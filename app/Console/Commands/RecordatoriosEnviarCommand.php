<?php

namespace App\Console\Commands;

use App\Services\Recordatorios\MotorEnvio;
use App\Services\Recordatorios\SincronizadorEventosBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recordatorios:enviar {--una-vez : Evalúa una sola vez y termina}')]
#[Description('Envía, si toca, los recordatorios de la cola por WhatsApp.')]
class RecordatoriosEnviarCommand extends Command
{
    /** Segundos que dura cada ejecución; el planificador la lanza cada minuto. */
    private const VENTANA_SEGUNDOS = 55;

    /** Cada cuántos segundos se evalúa si ya toca el siguiente mensaje. */
    private const INTERVALO_SEGUNDOS = 3;

    /**
     * El planificador solo llega a una granularidad de un minuto, y la espera
     * entre mensajes se cuenta en segundos (60 a 180): si se evaluara una sola
     * vez por minuto, cada espera se redondearía hacia arriba y la cuenta
     * regresiva del panel no coincidiría con el envío real. Por eso cada
     * ejecución se queda ~55 s evaluando cada 3 s. El motor decide si toca
     * (horario, espera, descanso). De paso trae el historial de conexión del
     * bot, así queda registrado aunque nadie tenga abierto el dashboard.
     */
    public function handle(MotorEnvio $motor, SincronizadorEventosBot $eventos): int
    {
        $eventos->sincronizar();

        if ($this->option('una-vez')) {
            $this->info($motor->tick());

            return self::SUCCESS;
        }

        $limite = now()->addSeconds(self::VENTANA_SEGUNDOS);

        do {
            $resultado = $motor->tick();

            if ($resultado === 'enviado' || $resultado === 'fallido') {
                $this->info($resultado);
            }

            if (now()->addSeconds(self::INTERVALO_SEGUNDOS)->greaterThan($limite)) {
                break;
            }

            sleep(self::INTERVALO_SEGUNDOS);
        } while (true);

        return self::SUCCESS;
    }
}
