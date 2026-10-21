<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Vendedor => 'Vendedor',
        };
    }
}
