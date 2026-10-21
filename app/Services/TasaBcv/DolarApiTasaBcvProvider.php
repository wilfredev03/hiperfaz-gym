<?php

namespace App\Services\TasaBcv;

use App\Exceptions\TasaBcvNoDisponibleException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Obtiene la tasa BCV oficial desde la API pública de ve.dolarapi.com, que
 * publica el promedio de compra/venta del dólar oficial reportado por el
 * Banco Central de Venezuela.
 */
class DolarApiTasaBcvProvider implements TasaBcvProvider
{
    public function __construct(
        private readonly string $endpoint = 'https://ve.dolarapi.com/v1/dolares/oficial',
    ) {}

    public function obtener(): float
    {
        try {
            $response = Http::timeout(10)->get($this->endpoint);
        } catch (Throwable $e) {
            throw new TasaBcvNoDisponibleException(
                'No se pudo contactar la fuente de la tasa BCV: '.$e->getMessage(),
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new TasaBcvNoDisponibleException(
                "La fuente de la tasa BCV respondió con estado HTTP {$response->status()}.",
            );
        }

        if (! $this->esDeHoy($response->json('fechaActualizacion'))) {
            throw new TasaBcvNoDisponibleException(
                'La fuente de la tasa BCV aún no ha publicado la tasa del día de hoy.',
            );
        }

        $tasa = $response->json('promedio');

        if (! is_numeric($tasa) || $tasa <= 0) {
            throw new TasaBcvNoDisponibleException(
                'La fuente de la tasa BCV no devolvió un valor numérico válido.',
            );
        }

        // la tasa se opera siempre con 2 decimales (ver negocio), aunque la
        // fuente devuelva más precisión
        return round((float) $tasa, 2);
    }

    /**
     * El BCV solo publica una tasa por día hábil: si `fechaActualizacion`
     * no corresponde al día de hoy en Venezuela, la fuente todavía no ha
     * actualizado el dato (por ejemplo, fin de semana o feriado) y no debe
     * usarse como la tasa vigente.
     */
    private function esDeHoy(mixed $fechaActualizacion): bool
    {
        if (! is_string($fechaActualizacion)) {
            return false;
        }

        try {
            $fecha = Date::parse($fechaActualizacion)->setTimezone('America/Caracas')->toDateString();
        } catch (Throwable) {
            return false;
        }

        return $fecha === Date::now('America/Caracas')->toDateString();
    }
}
