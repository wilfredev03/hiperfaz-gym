<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Freno de seguridad: RefreshDatabase borra y re-migra la base de datos
     * configurada antes de cada test. Si `bootstrap/cache/config.php` quedó
     * cacheado con un valor viejo (`config:cache`), Laravel ignora `.env` y
     * las variables de `phpunit.xml`, y los tests terminan corriendo —y
     * borrando— la base de datos real del negocio en vez de la de pruebas.
     *
     * Este chequeo corre justo después de crear/bootear la app (que es
     * cuando el config cacheado ya se resolvió) pero antes de que
     * RefreshDatabase (que se engancha más tarde, en setUpTraits) toque
     * nada, así que aborta a tiempo en vez de después del daño.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $conexion = $app['config']->get('database.default');
        $baseDeDatos = $app['config']->get("database.connections.{$conexion}.database");

        if (! str_contains((string) $baseDeDatos, 'test')) {
            throw new RuntimeException(
                "Los tests van a correr sobre la base de datos '{$baseDeDatos}', que no parece ser una base de prueba. ".
                'Esto casi siempre pasa porque bootstrap/cache/config.php quedó cacheado con un valor viejo (config:cache) '.
                "e ignora phpunit.xml. Corre `php artisan config:clear` y vuelve a intentar."
            );
        }

        return $app;
    }
}
