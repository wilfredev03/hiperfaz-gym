<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: #111111;
            }
        </style>

        {{-- Ícono de pestaña de esta instalación (config/branding.php). Sin
        BRAND_FAVICON_URL_LIGHT/_DARK configurados, cae al genérico de
        Laravel en vez de mostrar el de otro negocio. --}}
        @php
            $faviconLight = config('branding.favicon_url_light');
            $faviconDark = config('branding.favicon_url_dark');
        @endphp
        @if ($faviconLight || $faviconDark)
            @if ($faviconLight)
                <link rel="icon" type="image/png" href="{{ $faviconLight }}" media="(prefers-color-scheme: light)">
            @endif
            @if ($faviconDark)
                <link rel="icon" type="image/png" href="{{ $faviconDark }}" media="(prefers-color-scheme: dark)">
            @endif
            <link rel="apple-touch-icon" href="{{ $faviconLight ?? $faviconDark }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        {{-- Colores de marca de esta instalación (config/branding.php, uno por
        .env por cliente). Solo emite lo que esté definido; lo demás sigue la
        paleta neutra por defecto de resources/css/app.css. --}}
        @php $brand = array_filter(config('branding.colors')); @endphp
        @if ($brand)
            <style>
                :root {
                    @foreach ($brand as $key => $value)
                        --brand-{{ str_replace('_', '-', $key) }}: {{ $value }};
                    @endforeach
                }
            </style>
        @endif
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
