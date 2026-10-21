<?php

namespace App\Mail;

use App\Models\CierreCaja;
use App\Models\Sucursal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CierreCajaCerrado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CierreCaja $cierreCaja)
    {
        $this->cierreCaja->loadMissing([
            'usuario',
            'detalleMetodos.metodoPago',
            'ventasCredito.cliente',
            'ventasCredito.tasaCambio',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cierre de caja del '.$this->cierreCaja->fecha->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        $sucursal = Sucursal::where('activa', true)->orderBy('id')->first();

        return new Content(
            view: 'emails.cierre-caja-cerrado',
            with: [
                'cierre' => $this->cierreCaja,
                'negocio' => [
                    'nombre' => config('app.name'),
                    'telefono' => $sucursal?->telefono,
                    'direccion' => $sucursal?->direccion,
                ],
                'logoPath' => $this->rutaDelLogo('branding.logo_url_light'),
            ],
        );
    }

    /**
     * El logo se incrusta en el correo (CID) en vez de enlazarlo por URL:
     * con un APP_URL local o privado, Gmail/Outlook no podrían descargarlo.
     * Es un solo logo (el de fondo claro): la cabecera del correo es siempre
     * blanca, también en tema oscuro, para que se lea igual en cualquier cliente.
     */
    private function rutaDelLogo(string $clave): ?string
    {
        $url = config($clave);

        if (! $url) {
            return null;
        }

        $ruta = public_path(ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/'));

        return is_file($ruta) ? $ruta : null;
    }
}
