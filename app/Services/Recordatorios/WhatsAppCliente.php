<?php

namespace App\Services\Recordatorios;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Único punto donde Laravel habla con el bot de WhatsApp (whatsapp-bot/).
 * Nada más del dominio conoce el servicio: si mañana se cambia Baileys por
 * la API oficial, solo se reemplaza esta clase.
 */
class WhatsAppCliente
{
    /**
     * Estado de la sesión: `conectado`, `estado` (conectado | esperando_qr |
     * conectando | desconectado | qr_expirado | sin_servicio | no_autorizado),
     * `qr` (imagen en data URL mientras espera el escaneo), `qr_expira_en`
     * (segundos que le quedan a esa ventana) y `numero`. Nunca lanza: si el
     * servicio no responde devuelve `sin_servicio`.
     *
     * @return array{conectado: bool, estado: string, qr: string|null, qr_expira_en: int|null, numero: string|null}
     */
    public function estado(): array
    {
        try {
            $respuesta = $this->http()->timeout(4)->get('estado');
        } catch (ConnectionException) {
            return $this->sinServicio();
        }

        // el bot está vivo pero rechaza nuestro token: se distingue de "apagado"
        // porque la solución es otra (WHATSAPP_TOKEN distinto en cada .env)
        if (in_array($respuesta->status(), [401, 403], true)) {
            return ['conectado' => false, 'estado' => 'no_autorizado', 'qr' => null, 'qr_expira_en' => null, 'numero' => null];
        }

        if (! $respuesta->successful()) {
            return $this->sinServicio();
        }

        return [
            'conectado' => (bool) $respuesta->json('conectado'),
            'estado' => (string) $respuesta->json('estado', 'desconectado'),
            'qr' => $respuesta->json('qr'),
            'qr_expira_en' => $respuesta->json('qr_expira_en'),
            'numero' => $respuesta->json('numero'),
        ];
    }

    /**
     * Eventos de la sesión posteriores a `$despues` (el contador del bot), con
     * el último id que tiene. Null si el servicio no responde.
     *
     * @return array{ultimo_id: int, eventos: list<array<string, mixed>>}|null
     */
    public function eventos(int $despues): ?array
    {
        try {
            $respuesta = $this->http()->timeout(5)->get('eventos', ['despues' => $despues]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        return [
            'ultimo_id' => (int) $respuesta->json('ultimo_id', 0),
            'eventos' => $respuesta->json('eventos', []),
        ];
    }

    /**
     * Envía un texto a un número en formato internacional (58414XXXXXXX).
     *
     * @return array{id: string|null}
     *
     * @throws WhatsAppException
     */
    public function enviar(string $telefono, string $mensaje): array
    {
        try {
            $respuesta = $this->http()->post('enviar', ['telefono' => $telefono, 'mensaje' => $mensaje]);
        } catch (ConnectionException) {
            throw new WhatsAppException('No hay conexión con el servicio de WhatsApp.');
        }

        // 422: el número no tiene WhatsApp; reintentar no cambia nada
        if ($respuesta->status() === 422) {
            throw new WhatsAppException((string) $respuesta->json('error', 'El número no tiene WhatsApp.'), definitivo: true);
        }

        if (! $respuesta->successful()) {
            throw new WhatsAppException((string) $respuesta->json('error', 'El servicio de WhatsApp respondió con un error.'));
        }

        return ['id' => $respuesta->json('id')];
    }

    /** Descarta el intento actual y pide un QR nuevo (o reconecta con la sesión guardada). */
    public function reconectar(): bool
    {
        try {
            return $this->http()->post('reconectar')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /** Cierra la sesión de WhatsApp para vincular otro número (pedirá un QR nuevo). */
    public function desconectar(): bool
    {
        try {
            return $this->http()->post('desconectar')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('whatsapp.url'))
            ->withHeaders(['X-Token' => (string) config('whatsapp.token')])
            ->timeout((int) config('whatsapp.timeout'))
            ->acceptJson();
    }

    /** @return array{conectado: bool, estado: string, qr: string|null, qr_expira_en: int|null, numero: string|null} */
    private function sinServicio(): array
    {
        return ['conectado' => false, 'estado' => 'sin_servicio', 'qr' => null, 'qr_expira_en' => null, 'numero' => null];
    }
}
