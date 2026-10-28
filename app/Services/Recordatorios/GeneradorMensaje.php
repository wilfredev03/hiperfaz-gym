<?php

namespace App\Services\Recordatorios;

use App\Models\RecordatorioPlantilla;
use Illuminate\Support\Arr;

class GeneradorMensaje
{
    /** Variables que una plantilla puede usar, para mostrarlas en la pantalla de plantillas. */
    public const VARIABLES = [
        'nombre' => 'Primer nombre del cliente',
        'nombre_completo' => 'Nombre completo',
        'plan' => 'Nombre del plan',
        'dias' => 'Días que faltan ("3 días", "1 día" o "hoy")',
        'fecha' => 'Fecha de vencimiento (dd/mm/aaaa)',
    ];

    /**
     * Arma el mensaje de una plantilla. Primero resuelve las alternativas
     * `[Hola|Buenas|Qué tal]` (elige una al azar, así dos mensajes de la misma
     * plantilla no salen idénticos) y luego sustituye las variables `{nombre}`.
     *
     * @param  array<string, string>  $variables
     */
    public function generar(RecordatorioPlantilla $plantilla, array $variables): string
    {
        $texto = $this->elegirAlternativas($plantilla->cuerpo);

        $reemplazos = [];
        foreach ($variables as $nombre => $valor) {
            $reemplazos['{'.$nombre.'}'] = $valor;
        }

        return trim(strtr($texto, $reemplazos));
    }

    /** "Juan Carlos Pérez" → "Juan"; tolera nombres en mayúsculas o minúsculas. */
    public static function primerNombre(string $nombreCompleto): string
    {
        $primero = preg_split('/\s+/', trim($nombreCompleto))[0] ?? '';

        return mb_convert_case($primero, MB_CASE_TITLE, 'UTF-8');
    }

    /** "3 días", "1 día" o "hoy" según los días que faltan. */
    public static function textoDias(int $dias): string
    {
        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => '1 día',
            default => "{$dias} días",
        };
    }

    private function elegirAlternativas(string $texto): string
    {
        return preg_replace_callback(
            '/\[([^\[\]|]+(?:\|[^\[\]|]+)+)\]/u',
            fn (array $coincidencia) => Arr::random(explode('|', $coincidencia[1])),
            $texto,
        );
    }
}
