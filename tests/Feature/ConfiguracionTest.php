<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use App\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Periodo de alerta de caducidad configurable (Requerimientos RF-08, §9.10).
 */
class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_periodo_inicial_es_de_30_dias(): void
    {
        $this->assertSame(30, (new ConfiguracionService)->diasAlertaCaducidad());
    }

    public function test_sin_valor_guardado_se_usan_30_dias(): void
    {
        Configuracion::query()->delete();

        $this->assertSame(30, (new ConfiguracionService)->diasAlertaCaducidad());
    }

    public function test_el_administrador_cambia_el_periodo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('configuracion.edit'))->assertOk()->assertSee('value="30"', false);
        $this->actingAs($admin)->put(route('configuracion.update'), ['dias_alerta_caducidad' => '45'])
            ->assertRedirect(route('configuracion.edit'))
            ->assertSessionHas('status');

        $this->assertSame(45, (new ConfiguracionService)->diasAlertaCaducidad());
    }

    public function test_el_periodo_debe_estar_entre_1_y_365_dias(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['0', '366', '-5', 'treinta', ''] as $valor) {
            $this->actingAs($admin)->put(route('configuracion.update'), ['dias_alerta_caducidad' => $valor])
                ->assertSessionHasErrors('dias_alerta_caducidad');
        }

        $this->assertSame(30, (new ConfiguracionService)->diasAlertaCaducidad());
    }

    public function test_un_encargado_no_cambia_la_configuracion_aunque_tenga_todos_los_permisos_especiales(): void
    {
        $encargado = User::factory()
            ->conPermiso('puede_gestionar_catalogo')
            ->conPermiso('puede_ajustar_inventario')
            ->conPermiso('puede_revertir')
            ->create();

        $this->actingAs($encargado)->get(route('configuracion.edit'))->assertForbidden();
        $this->actingAs($encargado)->put(route('configuracion.update'), ['dias_alerta_caducidad' => '10'])->assertForbidden();
        $this->assertSame(30, (new ConfiguracionService)->diasAlertaCaducidad());
    }
}
