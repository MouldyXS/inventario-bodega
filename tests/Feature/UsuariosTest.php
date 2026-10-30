<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Gestión de usuarios, cambio de contraseña y cierre de sesiones
 * (Requerimientos §9.10, RNF-03).
 */
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'name' => 'Nuevo Encargado',
            'email' => 'nuevo@example.com',
            'password' => 'clave-inicial-1',
            'rol' => 'encargado',
            'puede_revertir' => '1',
            ...$cambios,
        ];
    }

    public function test_las_pantallas_de_usuarios_y_de_mi_cuenta_se_muestran(): void
    {
        $encargado = User::factory()->conPermiso('puede_revertir')->create();

        $this->actingAs($this->admin)->get(route('usuarios.create'))->assertOk()->assertSee('Contraseña inicial');
        $this->actingAs($this->admin)->get(route('usuarios.edit', $encargado))
            ->assertOk()
            ->assertSee($encargado->email)
            ->assertSee('Contraseña nueva')
            ->assertSee('Revertir movimientos');
        $this->actingAs($encargado)->get(route('cuenta.edit'))->assertOk()->assertSee('Cambiar mi contraseña');

        // El menú ofrece Usuarios solo al administrador.
        $this->actingAs($this->admin)->get(route('inicio'))->assertSee(route('usuarios.index'));
        $this->actingAs($encargado)->get(route('inicio'))->assertDontSee(route('usuarios.index'));
    }

    // --- Permisos -----------------------------------------------------------

    public function test_solo_el_administrador_gestiona_usuarios_aunque_el_encargado_tenga_todos_los_permisos(): void
    {
        $encargado = User::factory()
            ->conPermiso('puede_gestionar_catalogo')->conPermiso('puede_ajustar_inventario')->conPermiso('puede_revertir')
            ->create();

        $this->actingAs($encargado)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($encargado)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($encargado)->post(route('usuarios.store'), $this->datos())->assertForbidden();
        $this->actingAs($encargado)->get(route('usuarios.edit', $this->admin))->assertForbidden();
        $this->actingAs($encargado)->put(route('usuarios.update', $this->admin), $this->datos(['activo' => '1']))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
        $this->assertTrue($this->admin->fresh()->esAdmin());
    }

    // --- Alta y edición -------------------------------------------------------

    public function test_crear_un_encargado_con_permisos_y_contrasena_con_hash(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos())
            ->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nuevo@example.com')->sole();
        $this->assertSame(Rol::Encargado, $usuario->rol);
        $this->assertTrue($usuario->puede_revertir);
        $this->assertFalse($usuario->puede_gestionar_catalogo);
        $this->assertTrue($usuario->activo);
        $this->assertNotSame('clave-inicial-1', $usuario->password);
        $this->assertTrue(Hash::check('clave-inicial-1', $usuario->password));

        $this->actingAs($this->admin)->get(route('usuarios.index'))->assertSee('nuevo@example.com')->assertSee('Reversiones');
    }

    public function test_un_administrador_no_guarda_permisos_especiales(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos(['rol' => 'admin', 'puede_revertir' => '1']));

        $this->assertFalse(User::where('email', 'nuevo@example.com')->sole()->puede_revertir);
    }

    public function test_validaciones_del_alta(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos(['password' => 'corta']))->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos(['rol' => 'dios']))->assertSessionHasErrors('rol');
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos(['email' => $this->admin->email]))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post(route('usuarios.store'), $this->datos(['email' => 'no-es-correo']))->assertSessionHasErrors('email');
    }

    public function test_rol_y_permisos_no_se_asignan_en_masa_desde_otro_formulario(): void
    {
        $encargado = User::factory()->create();

        // La pantalla "Mi cuenta" solo cambia la contraseña: ignora cualquier otro campo.
        $this->actingAs($encargado)->put(route('cuenta.update'), [
            'password_actual' => 'password', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123',
            'rol' => 'admin', 'puede_revertir' => '1', 'activo' => '0',
        ])->assertSessionHasNoErrors();

        $encargado->refresh();
        $this->assertSame(Rol::Encargado, $encargado->rol);
        $this->assertFalse($encargado->puede_revertir);
        $this->assertTrue($encargado->activo);
    }

    public function test_editar_cambia_permisos_y_conserva_la_contrasena_si_se_deja_vacia(): void
    {
        $encargado = User::factory()->create();
        $hash = $encargado->password;

        $this->actingAs($this->admin)->put(route('usuarios.update', $encargado), $this->datos([
            'name' => 'Renombrado', 'email' => $encargado->email, 'password' => '', 'puede_ajustar_inventario' => '1', 'activo' => '1',
        ]))->assertRedirect(route('usuarios.index'));

        $encargado->refresh();
        $this->assertSame('Renombrado', $encargado->name);
        $this->assertTrue($encargado->puede_ajustar_inventario);
        $this->assertSame($hash, $encargado->password);
    }

    // --- Protecciones -----------------------------------------------------------

    public function test_nadie_se_desactiva_ni_se_quita_el_rol_de_administrador_a_si_mismo(): void
    {
        $otro = User::factory()->admin()->create();

        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), $this->datos(['email' => $this->admin->email, 'rol' => 'admin']))
            ->assertSessionHasErrors('activo');
        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), $this->datos(['email' => $this->admin->email, 'rol' => 'encargado', 'activo' => '1']))
            ->assertSessionHasErrors('rol');

        $admin = $this->admin->fresh();
        $this->assertTrue($admin->esAdmin() && $admin->activo);
        $this->assertTrue($otro->fresh()->activo);
    }

    public function test_siempre_queda_un_administrador_activo_aunque_dos_se_quiten_el_acceso_a_la_vez(): void
    {
        $b = User::factory()->admin()->create();
        $stale = User::find($b->id); // La petición de B ya pasó la autorización antes de que A lo degrade.

        // A degrada a B.
        $this->actingAs($this->admin)->put(route('usuarios.update', $b), $this->datos(['email' => $b->email, 'rol' => 'encargado', 'activo' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertFalse($b->fresh()->esAdmin());

        // B (con su sesión desactualizada) intenta degradar a A, que sería el último administrador.
        try {
            (new UsuarioService)->actualizar($stale, $this->admin, $this->datos(['email' => $this->admin->email, 'rol' => 'encargado', 'activo' => true]));
            $this->fail('Se esperaba que quedara al menos un administrador activo.');
        } catch (ValidationException $e) {
            $this->assertSame('Debe quedar al menos un administrador activo.', $e->errors()['rol'][0]);
        }

        $this->assertTrue($this->admin->fresh()->esAdmin());
    }

    public function test_desactivar_a_un_usuario_cierra_sus_sesiones_abiertas(): void
    {
        $encargado = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'sesion-encargado', 'user_id' => $encargado->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'sesion-admin', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->put(route('usuarios.update', $encargado), $this->datos(['email' => $encargado->email]));

        $this->assertFalse($encargado->fresh()->activo);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-encargado']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesion-admin']);
    }

    // --- Mi cuenta --------------------------------------------------------------

    public function test_cada_usuario_cambia_su_contrasena_con_la_actual(): void
    {
        $encargado = User::factory()->create();

        $this->actingAs($encargado)->put(route('cuenta.update'), ['password_actual' => 'incorrecta', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123'])
            ->assertSessionHasErrors('password_actual');
        $this->actingAs($encargado)->put(route('cuenta.update'), ['password_actual' => 'password', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');
        $this->actingAs($encargado)->put(route('cuenta.update'), ['password_actual' => 'password', 'password' => 'otra-clave-123', 'password_confirmation' => 'distinta'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $encargado->fresh()->password));

        $this->actingAs($encargado)->put(route('cuenta.update'), ['password_actual' => 'password', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123'])
            ->assertRedirect(route('cuenta.edit'));
        $this->assertTrue(Hash::check('otra-clave-123', $encargado->fresh()->password));
    }

    // --- Cierre de sesiones --------------------------------------------------------

    public function test_cerrar_sesiones_de_todos_o_de_un_usuario(): void
    {
        $encargado = User::factory()->create();
        foreach ([['s1', $encargado->id], ['s2', $encargado->id], ['s3', $this->admin->id]] as [$id, $usuario]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuario, 'payload' => '', 'last_activity' => time()]);
        }

        $this->artisan('inventario:cerrar-sesiones', ['correo' => $encargado->email])->expectsOutput('Sesiones cerradas: 2.')->assertSuccessful();
        $this->assertSame(['s3'], DB::table('sessions')->pluck('id')->all());

        $this->artisan('inventario:cerrar-sesiones', ['correo' => 'nadie@example.com'])->assertFailed();

        $this->artisan('inventario:cerrar-sesiones')->expectsOutput('Sesiones cerradas: 1.')->assertSuccessful();
        $this->assertSame(0, DB::table('sessions')->count());
    }
}
