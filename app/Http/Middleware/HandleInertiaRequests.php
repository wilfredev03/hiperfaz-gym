<?php

namespace App\Http\Middleware;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // vacíos mientras la instalación no suba su propio logo (ver
            // config/branding.php) — AppLogo.vue/AppLogoIcon.vue caen a un
            // genérico en ese caso
            'logoUrlLight' => config('branding.logo_url_light'),
            'logoUrlDark' => config('branding.logo_url_dark'),
            'iconUrlLight' => config('branding.icon_url_light'),
            'iconUrlDark' => config('branding.icon_url_dark'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Hoy cada negocio (carpeta + BD + dominio) opera con una sola
            // sucursal, así que basta con tomar la primera activa. Cuando
            // haya selección de sucursal por sesión/usuario, esta línea es
            // el único lugar que hay que tocar.
            'sucursalActiva' => Sucursal::where('activa', true)
                ->orderBy('id')
                ->first(['id', 'nombre', 'termino_orden', 'direccion', 'telefono']),
        ];
    }
}
