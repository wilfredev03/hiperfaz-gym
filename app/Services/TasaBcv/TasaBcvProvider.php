<?php

namespace App\Services\TasaBcv;

use App\Exceptions\TasaBcvNoDisponibleException;

interface TasaBcvProvider
{
    /**
     * Obtiene la tasa de cambio BCV (Bs. por USD) publicada para el día.
     *
     * @throws TasaBcvNoDisponibleException si la fuente no responde o el valor recibido no es válido.
     */
    public function obtener(): float;
}
