<?php

namespace App\Providers;

use App\Services\TasaBcv\BcvScrapingTasaBcvProvider;
use App\Services\TasaBcv\DolarApiTasaBcvProvider;
use App\Services\TasaBcv\FallbackTasaBcvProvider;
use App\Services\TasaBcv\TasaBcvProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TasaBcvProvider::class, fn () => new FallbackTasaBcvProvider([
            new BcvScrapingTasaBcvProvider,
            new DolarApiTasaBcvProvider,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // detrás de un proxy TLS (desarrollo con HTTPS o el servidor) la app ve
        // la petición como http: si APP_URL es https, todos los enlaces,
        // redirecciones y assets se generan también con https
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // mínimo 4 caracteres en todos los entornos: decisión de negocio (los
        // usuarios los crea el admin y son personal del local)
        Password::defaults(fn (): Password => Password::min(4));

        $this->configureDevServer();
    }

    /**
     * `php artisan dev` (y por tanto `composer run dev`) registra
     * `php artisan serve` sin --host: por defecto solo escucha en 127.0.0.1,
     * así que el sistema no carga ni desde el IP de la red local (celular,
     * tablet, otra PC) ni abriendo esa IP en el propio navegador de esta PC.
     * Se expone en todas las interfaces para que coincida con Vite, que ya
     * escucha en todas (ver vite.config.js: server.host/cors).
     */
    protected function configureDevServer(): void
    {
        if ($this->app->runningInConsole() && $this->app->environment('local')) {
            DevCommands::artisan('serve --host=0.0.0.0', 'server');
        }
    }
}
