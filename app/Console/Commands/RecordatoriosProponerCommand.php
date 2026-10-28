<?php

namespace App\Console\Commands;

use App\Services\Recordatorios\RecordatorioService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recordatorios:proponer')]
#[Description('Deja en la cola los avisos de membresías por vencer y que vencen hoy.')]
class RecordatoriosProponerCommand extends Command
{
    /**
     * Solo llena la cola con avisos `pendiente`; el envío lo hace
     * `recordatorios:enviar` cuando el dueño pone en marcha el bot.
     */
    public function handle(RecordatorioService $recordatorios): int
    {
        $r = $recordatorios->proponer();

        $this->info("Avisos creados: {$r['creados']}. Sin teléfono válido: {$r['sin_telefono']}. Sin plantilla: {$r['sin_plantilla']}. Ya propuestos: {$r['ya_propuestos']}.");

        return self::SUCCESS;
    }
}
