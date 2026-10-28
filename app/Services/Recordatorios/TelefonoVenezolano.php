<?php

namespace App\Services\Recordatorios;

class TelefonoVenezolano
{
    /** Prefijos de celular de Venezuela (sin el 0 inicial). */
    private const OPERADORAS = ['412', '414', '416', '424', '426'];

    /**
     * Deja un teléfono en formato internacional sin "+" (58414XXXXXXX), que es
     * lo que pide WhatsApp. Acepta 0414-1234567, +58 414 1234567, 4141234567,
     * etc. Devuelve null si no es un celular venezolano válido (un fijo o un
     * número mal digitado no sirve para WhatsApp).
     */
    public static function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        if (str_starts_with($digitos, '58')) {
            $digitos = substr($digitos, 2);
        }

        $digitos = ltrim($digitos, '0');

        if (strlen($digitos) !== 10 || ! in_array(substr($digitos, 0, 3), self::OPERADORAS, true)) {
            return null;
        }

        return '58'.$digitos;
    }
}
