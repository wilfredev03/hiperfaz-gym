<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('suscripciones:marcar-vencidas')]
#[Description('Pasa a "vencida" las suscripciones activas cuyo último día de acceso ya pasó.')]
class MarcarSuscripcionesVencidasCommand extends Command
{
    /**
     * El acceso nunca depende de este comando: `Suscripcion::vigentes()` y la
     * vigencia del socio se calculan por fechas. Esto solo mantiene el campo
     * `estado` al día. Es el único cambio permitido sobre una suscripción
     * (principio #10): las fechas no se tocan.
     */
    public function handle(): int
    {
        $marcadas = Suscripcion::query()
            ->where('estado', 'activa')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);

        $this->info("Suscripciones marcadas como vencidas: {$marcadas}.");

        return self::SUCCESS;
    }
}
