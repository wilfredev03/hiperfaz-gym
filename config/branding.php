<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Colores de marca por instalación
    |--------------------------------------------------------------------------
    |
    | Cada cliente (un gimnasio, otro negocio...) es su propia instalación
    | aislada (ver CLAUDE.md), con su propio .env. Estas variables permiten
    | que cada una defina sus colores de marca sin tocar el código
    | compartido. Si una queda vacía, resources/css/app.css usa un valor
    | neutro por defecto (paleta blanco/negro de shadcn).
    |
    | Acepta cualquier color válido en CSS: hex (#a8460f), hsl(...), oklch(...).
    |
    */

    // agrupados aparte (no sueltos al nivel raíz) porque app.blade.php
    // recorre este array completo para generar las variables CSS
    // --brand-*; si una ruta de imagen se colara ahí generaría una variable
    // CSS sin sentido.
    'colors' => [
        'background' => env('BRAND_BACKGROUND'),
        'foreground' => env('BRAND_FOREGROUND'),

        'primary' => env('BRAND_PRIMARY'),
        'primary_foreground' => env('BRAND_PRIMARY_FOREGROUND'),

        'secondary' => env('BRAND_SECONDARY'),
        'secondary_foreground' => env('BRAND_SECONDARY_FOREGROUND'),

        'sidebar_background' => env('BRAND_SIDEBAR_BACKGROUND'),
        'sidebar_foreground' => env('BRAND_SIDEBAR_FOREGROUND'),
    ],

    // rutas públicas del logo subido por esta instalación (ej.
    // /logo/logo_light.png), una para fondo claro y otra para
    // fondo oscuro — no son la misma imagen invertida por CSS, son artes
    // distintas. Vacío = AppLogo.vue muestra un genérico en vez del logo de
    // otro negocio.
    'logo_url_light' => env('BRAND_LOGO_URL_LIGHT'),
    'logo_url_dark' => env('BRAND_LOGO_URL_DARK'),

    // mismo criterio que el logo, para la versión compacta (ícono sin
    // wordmark) que se usa en la barra lateral colapsada y el menú móvil.
    'icon_url_light' => env('BRAND_ICON_URL_LIGHT'),
    'icon_url_dark' => env('BRAND_ICON_URL_DARK'),

    // ícono de pestaña del navegador. Igual que el logo, una imagen por
    // tema — app.blade.php la sirve según prefers-color-scheme del sistema
    // operativo (no puede seguir el selector de apariencia manual de la
    // app, que es solo de la página ya cargada). Vacío = favicon genérico
    // de Laravel (public/favicon.ico / favicon.svg) en vez del de otro
    // negocio.
    'favicon_url_light' => env('BRAND_FAVICON_URL_LIGHT'),
    'favicon_url_dark' => env('BRAND_FAVICON_URL_DARK'),

];
