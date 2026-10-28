<?php

namespace App\Services\Recordatorios;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    /**
     * @param  bool  $definitivo  true si reintentar no sirve (ej. el número no tiene WhatsApp)
     */
    public function __construct(string $mensaje, public readonly bool $definitivo = false)
    {
        parent::__construct($mensaje);
    }
}
