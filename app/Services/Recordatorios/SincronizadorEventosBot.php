<?php

namespace App\Services\Recordatorios;

use App\Models\WhatsAppEvento;
use Illuminate\Support\Carbon;

/**
 * Trae del bot los eventos de la sesión (QR, conexión, caídas, reconexiones) y
 * los guarda en `whatsapp_eventos`. El bot los guarda en un archivo y Laravel
 * los consulta con un cursor, así no se pierden si Laravel estuvo caído un rato
 * y no hace falta exponer ninguna ruta pública para que el bot avise.
 * Es seguro llamarlo las veces que sea: un evento ya guardado se ignora.
 */
class SincronizadorEventosBot
{
    public function __construct(private WhatsAppCliente $bot) {}

    /** @return int cuántos eventos nuevos se guardaron */
    public function sincronizar(): int
    {
        $cursor = (int) WhatsAppEvento::max('evento_id');
        $respuesta = $this->bot->eventos($cursor);

        if ($respuesta === null) {
            return 0;
        }

        // el contador del bot es menor que lo que ya guardamos: reinstalaron el
        // bot desde cero y su numeración empezó de nuevo
        if ($respuesta['ultimo_id'] < $cursor) {
            $respuesta = $this->bot->eventos(0);

            if ($respuesta === null) {
                return 0;
            }
        }

        $filas = collect($respuesta['eventos'])->map(fn (array $evento) => [
            'evento_id' => $evento['id'],
            'tipo' => $evento['tipo'],
            'detalle' => $evento['detalle'] ?? null,
            'numero' => $evento['numero'] ?? null,
            'codigo' => $evento['codigo'] ?? null,
            'ocurrido_en' => Carbon::parse($evento['ocurrido_en'])->setTimezone(config('app.timezone'))->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
        ])->all();

        return $filas ? WhatsAppEvento::query()->insertOrIgnore($filas) : 0;
    }
}
