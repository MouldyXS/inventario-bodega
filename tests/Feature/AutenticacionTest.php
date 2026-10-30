<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_enviado_al_inicio_de_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_inicio_de_sesion_se_muestra(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Contraseña');
    }

    public function test_un_usuario_activo_inicia_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_contrasena_incorrecta_no_inicia_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_desactivado_pierde_su_sesion_en_la_siguiente_peticion(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get('/')->assertOk();

        $usuario->activo = false;
        $usuario->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_se_bloquea_tras_demasiados_intentos_fallidos(): void
    {
        $this->freezeTime();
        $usuario = User::factory()->create();

        for ($i = 0; $i < LoginController::MAX_INTENTOS; $i++) {
            $this->post(route('login'), ['email' => $usuario->email, 'password' => 'incorrecta']);
        }

        // Ni siquiera la contraseña correcta entra mientras dura el bloqueo.
        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Demasiados intentos. Vuelve a intentarlo en 60 segundos.']);

        $this->assertGuest();
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
