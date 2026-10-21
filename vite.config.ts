import { readFileSync } from 'node:fs';
import { networkInterfaces } from 'node:os';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

try {
    process.loadEnvFile('.env');
} catch {}

// HTTPS de desarrollo (VITE_HTTPS=true): la hoja nativa de compartir del
// celular/tablet solo existe en páginas https, y una página https no puede
// cargar el JS de Vite por http (contenido mixto). Los certificados se
// generan con scripts/generar-certificado-dev.sh.
const https = process.env.VITE_HTTPS === 'true';

// Con host: true Vite escucha en [::] y laravel-vite-plugin escribe esa
// dirección en public/hot, que el navegador no puede abrir
// (ERR_ADDRESS_INVALID). Se fija el origin con VITE_DEV_HOST o, si no está,
// con la primera IPv4 de la PC en la red actual (cambia al cambiar de red).
const hostDev =
    process.env.VITE_DEV_HOST ||
    Object.values(networkInterfaces())
        .flat()
        .find((i) => i && i.family === 'IPv4' && !i.internal)?.address;

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Poppins', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        host: true,
        // puerto propio: 5173 (el de Vite por defecto) suele estar ocupado por
        // otro proyecto y, sin strictPort, el navegador termina pidiendo el JS
        // a ese otro servidor (sin CORS para este origen)
        port: 5174,
        strictPort: true,
        // el sistema se abre por IP de la red (p. ej. 172.20.21.84:8000), no
        // solo por localhost: sin esto Vite bloquea el JS por CORS y el
        // formulario se envía sin token CSRF (419)
        cors: true,
        https: https
            ? {
                  key: readFileSync('storage/ssl/dev.key'),
                  cert: readFileSync('storage/ssl/dev.crt'),
              }
            : undefined,
        origin: hostDev
            ? `${https ? 'https' : 'http'}://${hostDev}:5174`
            : undefined,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
