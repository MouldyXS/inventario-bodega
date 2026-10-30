<?php

namespace Tests\Feature\Inventario;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/**
 * Concurrencia real con procesos PHP independientes (Requerimientos §5.6, §12).
 *
 * Las clases que lo usan no usan RefreshDatabase: los otros procesos no verían
 * los datos de una transacción abierta. Los datos se confirman y se borran al
 * terminar cada prueba.
 */
trait LanzaProcesos
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        // Deja la base vacía para las clases que usan RefreshDatabase.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    /**
     * Lanza un proceso por trabajo, todos a la vez, y devuelve la respuesta de cada uno.
     *
     * @param  list<array<string, mixed>>  $trabajos  Datos para tests/Concurrencia/trabajador.php.
     * @return list<array{ok: bool, movimiento?: int, reenvio?: bool, rechazado?: bool, errores?: array<string, list<string>>, error?: string}>
     */
    protected function lanzar(array $trabajos): array
    {
        // Margen para que todos los procesos terminen de arrancar Laravel.
        $inicio = microtime(true) + 3;

        $resultados = Process::pool(function (Pool $pool) use ($trabajos, $inicio) {
            foreach ($trabajos as $trabajo) {
                $pool->path(base_path())
                    ->env(['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'inventario_test'])
                    ->timeout(60)
                    ->command([PHP_BINARY, 'tests/Concurrencia/trabajador.php', json_encode([...$trabajo, 'inicio' => $inicio])]);
            }
        })->start()->wait();

        return collect($resultados)
            ->map(fn ($resultado) => json_decode($resultado->output(), true) ?? ['ok' => false, 'error' => $resultado->errorOutput()])
            ->values()
            ->all();
    }

    /**
     * Ningún proceso debe fallar por algo que no sea una regla de negocio
     * (interbloqueos no resueltos, errores 1062 sin clasificar…).
     *
     * @param  list<array<string, mixed>>  $respuestas
     */
    protected function assertSinFallos(array $respuestas): void
    {
        foreach ($respuestas as $respuesta) {
            $this->assertTrue($respuesta['ok'] || ($respuesta['rechazado'] ?? false), $respuesta['error'] ?? '');
        }
    }
}
