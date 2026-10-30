<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_administrador(): void
    {
        $this->artisan('usuarios:crear')
            ->expectsQuestion('Nombre', 'Administradora')
            ->expectsQuestion('Correo', 'admin@example.com')
            ->expectsChoice('Rol', 'admin', ['admin', 'encargado'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secreto123')
            ->expectsQuestion('Repite la contraseña', 'secreto123')
            ->assertSuccessful();

        $usuario = User::where('email', 'admin@example.com')->sole();
        $this->assertSame(Rol::Admin, $usuario->rol);
        $this->assertTrue(Hash::check('secreto123', $usuario->password));
    }

    public function test_crea_un_encargado_con_los_permisos_elegidos(): void
    {
        $this->artisan('usuarios:crear')
            ->expectsQuestion('Nombre', 'Encargado')
            ->expectsQuestion('Correo', 'bodega@example.com')
            ->expectsChoice('Rol', 'encargado', ['admin', 'encargado'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secreto123')
            ->expectsQuestion('Repite la contraseña', 'secreto123')
            ->expectsConfirmation('¿Puede gestionar el catálogo?', 'yes')
            ->expectsConfirmation('¿Puede ajustar el inventario y corregir lotes?', 'no')
            ->expectsConfirmation('¿Puede revertir movimientos?', 'no')
            ->assertSuccessful();

        $usuario = User::where('email', 'bodega@example.com')->sole();
        $this->assertSame(Rol::Encargado, $usuario->rol);
        $this->assertTrue($usuario->puede_gestionar_catalogo);
        $this->assertFalse($usuario->puede_ajustar_inventario);
        $this->assertFalse($usuario->puede_revertir);
    }

    public function test_rechaza_contrasenas_que_no_coinciden(): void
    {
        $this->artisan('usuarios:crear')
            ->expectsQuestion('Nombre', 'Encargado')
            ->expectsQuestion('Correo', 'bodega@example.com')
            ->expectsChoice('Rol', 'encargado', ['admin', 'encargado'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secreto123')
            ->expectsQuestion('Repite la contraseña', 'otra-cosa')
            ->expectsOutput('Las contraseñas no coinciden.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'bodega@example.com']);
    }
}
