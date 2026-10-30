<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El comando inventario:verificar-permisos detecta una cuenta con privilegios
 * de más (Requerimientos §6.5, Caso 26). La cuenta de pruebas tiene todos los
 * privilegios sobre inventario_test, así que el comando debe fallar.
 */
class VerificarPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_detecta_una_cuenta_que_puede_modificar_el_historial(): void
    {
        $this->artisan('inventario:verificar-permisos')
            ->expectsOutputToContain('Cuenta:')
            ->expectsOutputToContain('La cuenta no cumple §6.5.')
            ->assertFailed();
    }
}
