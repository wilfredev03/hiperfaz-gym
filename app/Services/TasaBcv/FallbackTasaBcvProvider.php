<?php

namespace App\Services\TasaBcv;

use App\Exceptions\TasaBcvNoDisponibleException;
use Illuminate\Support\Facades\Log;

/**
 * Consulta una lista ordenada de proveedores y devuelve la tasa del
 * primero que responda con éxito. Solo lanza una falla explícita cuando
 * ninguno de los proveedores configurados pudo obtener la tasa del día.
 */
class FallbackTasaBcvProvider implements TasaBcvProvider
{
    /**
     * @param  array<int, TasaBcvProvider>  $proveedores
     */
    public function __construct(private readonly array $proveedores) {}

    public function obtener(): float
    {
        $errores = [];

        foreach ($this->proveedores as $proveedor) {
            try {
                return $proveedor->obtener();
            } catch (TasaBcvNoDisponibleException $e) {
                $errores[] = $proveedor::class.': '.$e->getMessage();

                Log::warning('Un proveedor de la tasa BCV falló, se intentará el siguiente.', [
                    'proveedor' => $proveedor::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new TasaBcvNoDisponibleException(
            'Ningún proveedor de la tasa BCV pudo obtener la tasa del día: '.implode(' | ', $errores),
        );
    }
}
