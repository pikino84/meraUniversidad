<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication {
        createApplication as bootApplication;
    }

    /**
     * Se ejecuta ANTES que RefreshDatabase (que corre migrate:fresh),
     * así que es el único lugar seguro para el candado.
     */
    public function createApplication(): Application
    {
        $app = $this->bootApplication();

        $this->guardAgainstRealDatabase($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite(); // las vistas no dependen de que exista public/build
    }

    /**
     * Candado de seguridad: si las pruebas apuntan a una BD real, se aborta antes de borrar nada.
     */
    private function guardAgainstRealDatabase(Application $app): void
    {
        $config = $app['config'];
        $connection = $config->get('database.default');
        $database = (string) $config->get("database.connections.{$connection}.database");

        $isolated = ($connection === 'sqlite' && $database === ':memory:')
            || str_ends_with($database, '_testing');

        if (! $isolated) {
            throw new RuntimeException(
                "Pruebas abortadas: la conexión [{$connection}] apunta a [{$database}]. "
                .'Usa sqlite :memory: o una BD con sufijo _testing (ver phpunit.xml).'
            );
        }
    }
}
