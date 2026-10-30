<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requerimientos §2.3: roles base y permisos especiales.
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    private const GATES_ESPECIALES = ['gestionar-catalogo', 'ajustar-inventario', 'revertir'];

    public function test_el_administrador_tiene_todos_los_permisos(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([...self::GATES_ESPECIALES, 'administrar'] as $gate) {
            $this->assertTrue(Gate::forUser($admin)->allows($gate), $gate);
        }
    }

    public function test_un_encargado_sin_permisos_especiales_no_tiene_ninguno(): void
    {
        $encargado = User::factory()->create();

        foreach ([...self::GATES_ESPECIALES, 'administrar'] as $gate) {
            $this->assertFalse(Gate::forUser($encargado)->allows($gate), $gate);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function permisos(): array
    {
        return [
            'catálogo' => ['puede_gestionar_catalogo', 'gestionar-catalogo'],
            'ajustes' => ['puede_ajustar_inventario', 'ajustar-inventario'],
            'reversiones' => ['puede_revertir', 'revertir'],
        ];
    }

    #[DataProvider('permisos')]
    public function test_cada_permiso_especial_concede_solo_su_operacion(string $atributo, string $gateConcedido): void
    {
        $encargado = User::factory()->conPermiso($atributo)->create();

        foreach (self::GATES_ESPECIALES as $gate) {
            $this->assertSame($gate === $gateConcedido, Gate::forUser($encargado)->allows($gate), $gate);
        }
    }

    public function test_la_administracion_es_exclusiva_del_administrador(): void
    {
        $encargado = User::factory()
            ->conPermiso('puede_gestionar_catalogo')
            ->conPermiso('puede_ajustar_inventario')
            ->conPermiso('puede_revertir')
            ->create();

        $this->assertFalse(Gate::forUser($encargado)->allows('administrar'));
    }

    public function test_el_rol_y_los_permisos_no_son_asignables_en_masa(): void
    {
        $usuario = User::create([
            'name' => 'Intento',
            'email' => 'intento@example.com',
            'password' => 'secreto123',
            'rol' => 'admin',
            'puede_gestionar_catalogo' => true,
            'puede_ajustar_inventario' => true,
            'puede_revertir' => true,
            'activo' => false,
        ])->fresh();

        $this->assertSame(Rol::Encargado, $usuario->rol);
        $this->assertFalse($usuario->puede_gestionar_catalogo);
        $this->assertFalse($usuario->puede_ajustar_inventario);
        $this->assertFalse($usuario->puede_revertir);
        $this->assertTrue($usuario->activo);
    }
}
