<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Requerimientos §12: la suite se niega a ejecutarse contra una base cuyo
     * nombre no termine en `_test`, antes de que ninguna prueba toque la base.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $conexion = $app['config']->get('database.default');
        $base = (string) $app['config']->get("database.connections.{$conexion}.database");

        try {
            static::asegurarBaseDePruebas($conexion, $base);
        } catch (RuntimeException $e) {
            fwrite(STDERR, PHP_EOL.$e->getMessage().PHP_EOL);
            exit(1);
        }

        return $app;
    }

    public static function asegurarBaseDePruebas(string $conexion, string $base): void
    {
        if ($conexion !== 'mysql' || ! str_ends_with($base, '_test')) {
            throw new RuntimeException(
                "Pruebas detenidas: la conexión '{$conexion}' apunta a la base '{$base}'. "
                .'Las pruebas solo se ejecutan contra una base MySQL cuyo nombre termine en _test.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas no necesitan los recursos compilados por Vite.
        $this->withoutVite();
    }
}
