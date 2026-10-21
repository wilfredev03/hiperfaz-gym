<?php

namespace App\Console\Commands;

use App\Exceptions\TasaBcvNoDisponibleException;
use App\Models\TasaCambio;
use App\Services\TasaBcv\TasaBcvProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('tasas:obtener-bcv')]
#[Description('Obtiene la tasa BCV del día desde la fuente externa configurada y la registra como tasa automática.')]
class ObtenerTasaBcvCommand extends Command
{
    public function __construct(private readonly TasaBcvProvider $proveedor)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // se programa varias veces en la mañana como reintento; si ya hay
        // tasa de hoy (automática de un intento anterior o manual que cargó
        // alguien) no se toca, nunca se pisa una decisión de un usuario
        if ($vigente = TasaCambio::vigenteHoy()) {
            $this->info("Ya hay una tasa registrada para hoy ({$vigente->tipo}: {$vigente->tasa}), no se consulta la fuente.");

            return self::SUCCESS;
        }

        try {
            $tasa = $this->proveedor->obtener();
        } catch (TasaBcvNoDisponibleException $e) {
            Log::error('No se pudo obtener la tasa BCV automática del día.', [
                'error' => $e->getMessage(),
            ]);

            $this->error('No se pudo obtener la tasa BCV: '.$e->getMessage());

            return self::FAILURE;
        }

        $tasaCambio = TasaCambio::registrarNueva($tasa, 'automatica');

        $this->info("Tasa BCV registrada: {$tasaCambio->tasa} (fecha {$tasaCambio->fecha->toDateString()}).");

        return self::SUCCESS;
    }
}
