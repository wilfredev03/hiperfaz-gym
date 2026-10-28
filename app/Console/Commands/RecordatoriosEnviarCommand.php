<?php

namespace App\Console\Commands;

use App\Services\Recordatorios\MotorEnvio;
use App\Services\Recordatorios\SincronizadorEventosBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recordatorios:enviar')]
#[Description('Envía, si toca, el siguiente recordatorio de la cola por WhatsApp.')]
class RecordatoriosEnviarCommand extends Command
{
    /**
     * Corre cada minuto; el motor decide si ya es hora (horario, espera,
     * descanso). De paso trae el historial de conexión del bot, así queda
     * registrado aunque nadie tenga abierto el dashboard.
     */
    public function handle(MotorEnvio $motor, SincronizadorEventosBot $eventos): int
    {
        $eventos->sincronizar();

        $this->info($motor->tick());

        return self::SUCCESS;
    }
}
