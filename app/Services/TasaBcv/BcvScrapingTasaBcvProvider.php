<?php

namespace App\Services\TasaBcv;

use App\Exceptions\TasaBcvNoDisponibleException;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Obtiene la tasa BCV oficial haciendo scraping directo de la página
 * pública del Banco Central de Venezuela (www.bcv.org.ve), la fuente
 * primaria de la que derivan los espejos como dolarapi.com.
 */
class BcvScrapingTasaBcvProvider implements TasaBcvProvider
{
    /**
     * @param  string|null  $cadenaCertificados  PEM con la cadena de confianza del BCV; null usa
     *                                           resources/certs/bcv-cadena-certificados.pem (ver ese archivo)
     */
    public function __construct(
        private readonly string $endpoint = 'https://www.bcv.org.ve/',
        private readonly ?string $cadenaCertificados = null,
    ) {}

    public function obtener(): float
    {
        try {
            $response = Http::timeout(15)
                ->withOptions(['verify' => $this->cadenaCertificados ?? resource_path('certs/bcv-cadena-certificados.pem')])
                ->get($this->endpoint);
        } catch (Throwable $e) {
            throw new TasaBcvNoDisponibleException(
                'No se pudo contactar la página del BCV: '.$e->getMessage(),
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new TasaBcvNoDisponibleException(
                "La página del BCV respondió con estado HTTP {$response->status()}.",
            );
        }

        $xpath = $this->xpathDesde($response->body());

        if (! $this->esValidaParaHoy($xpath)) {
            throw new TasaBcvNoDisponibleException(
                'La página del BCV aún no ha publicado la tasa del día de hoy.',
            );
        }

        $tasa = $this->extraerTasa($xpath);

        if ($tasa === null) {
            throw new TasaBcvNoDisponibleException(
                'No se encontró el valor del dólar en la página del BCV (la estructura del HTML pudo haber cambiado).',
            );
        }

        // la tasa se opera siempre con 2 decimales (ver negocio), aunque el
        // BCV la publique con más precisión
        return round($tasa, 2);
    }

    private function xpathDesde(string $html): DOMXPath
    {
        $documento = new DOMDocument;

        libxml_use_internal_errors(true);
        $documento->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($documento);
    }

    /**
     * El valor del dólar vive en <div id="dolar"> ... <strong class="strong-tb">813,73610000</strong>,
     * con coma como separador decimal.
     */
    private function extraerTasa(DOMXPath $xpath): ?float
    {
        $nodos = $xpath->query('//div[@id="dolar"]//strong[contains(@class, "strong-tb")]');

        if ($nodos === false || $nodos->length === 0) {
            return null;
        }

        $valor = str_replace(',', '.', trim($nodos->item(0)->textContent));

        if (! is_numeric($valor) || (float) $valor <= 0) {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Debajo de los indicadores de moneda, un único bloque "Fecha Valor"
     * indica el día para el cual el BCV publicó las tasas mostradas. El BCV
     * publica en la tarde la tasa con "fecha valor" del próximo día hábil
     * (ver routes/console.php), así que entre semana esa fecha coincide con
     * "hoy" apenas amanece. El viernes en la tarde ese "próximo día hábil"
     * es el lunes: esa misma tasa (fechada a futuro) es la que debe regir
     * todo el fin de semana, así que en sábado y domingo se acepta
     * cualquier fecha publicada sin exigir que coincida con la de hoy.
     */
    private function esValidaParaHoy(DOMXPath $xpath): bool
    {
        $nodos = $xpath->query(
            '//div[contains(@class, "pull-right") and contains(@class, "dinpro")]'.
            '//span[contains(@class, "date-display-single")]/@content'
        );

        if ($nodos === false || $nodos->length === 0) {
            return false;
        }

        try {
            $fecha = Date::parse($nodos->item(0)->nodeValue)->setTimezone('America/Caracas');
        } catch (Throwable) {
            return false;
        }

        $hoy = Date::now('America/Caracas');

        if ($fecha->toDateString() === $hoy->toDateString()) {
            return true;
        }

        return $hoy->isWeekend();
    }
}
