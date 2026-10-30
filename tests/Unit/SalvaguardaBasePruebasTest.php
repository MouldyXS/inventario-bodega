<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestCase as TestCaseAplicacion;

/**
 * Caso 28: la suite se niega a ejecutarse contra una base que no termine en `_test`.
 */
class SalvaguardaBasePruebasTest extends TestCase
{
    public function test_acepta_la_base_de_pruebas(): void
    {
        TestCaseAplicacion::asegurarBaseDePruebas('mysql', 'inventario_test');

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_la_base_de_produccion(): void
    {
        $this->expectException(RuntimeException::class);

        TestCaseAplicacion::asegurarBaseDePruebas('mysql', 'inventario');
    }

    public function test_rechaza_una_conexion_que_no_sea_mysql(): void
    {
        $this->expectException(RuntimeException::class);

        TestCaseAplicacion::asegurarBaseDePruebas('sqlite', 'inventario_test');
    }
}
