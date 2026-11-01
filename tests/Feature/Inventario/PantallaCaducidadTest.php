<?php

namespace Tests\Feature\Inventario;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantalla de caducidad, contador del menú y estados en la ficha de inventario
 * (Requerimientos RF-08, §9.7). Hoy es el 30-10-2026; periodo de 30 días.
 */
class PantallaCaducidadTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->recibir($this->botella, 4, 'VIEJO', '2026-10-20');
        $this->recibir($this->caja, 2, 'PRONTO', '2026-11-10');
        $this->recibir($this->caja, 5, 'LEJANO', '2027-06-30');
    }

    public function test_por_defecto_muestra_vencidos_y_proximos_en_orden_de_caducidad(): void
    {
        $this->actingAs($this->usuario)->get(route('caducidad.index'))
            ->assertOk()
            ->assertSeeInOrder(['VIEJO', 'Vencido', 'Venció hace 10 días', '4 Botella', 'PRONTO', 'Próximo a caducar', 'Faltan 11 días', '2 Caja x12', 'Total: 24 Botella'])
            ->assertDontSee('LEJANO');
    }

    public function test_filtros_por_estado(): void
    {
        $this->actingAs($this->usuario)->get(route('caducidad.index', ['estado' => 'vigente']))
            ->assertSee('LEJANO')
            ->assertDontSee('VIEJO')
            ->assertDontSee('PRONTO');

        $this->actingAs($this->usuario)->get(route('caducidad.index', ['estado' => 'vencido']))
            ->assertSee('VIEJO')
            ->assertDontSee('PRONTO');

        $this->actingAs($this->usuario)->get(route('caducidad.index', ['estado' => 'todos', 'q' => 'fideos']))
            ->assertSee('No hay lotes con existencias en este estado.');
    }

    public function test_desde_un_lote_vencido_la_salida_llega_con_motivo_vencido(): void
    {
        $this->actingAs($this->usuario)->get(route('caducidad.index'))
            ->assertSee(route('salidas.create', ['producto' => $this->aceite->id, 'motivo' => 'vencido']));

        // El motivo es un grupo de botones (radios con name="motivo"): el elegido va marcado.
        $elegido = fn (string $html, string $valor) => preg_match('/<input type="radio"[^>]*name="motivo"[^>]*value="'.$valor.'"[^>]*\schecked/', $html) === 1;

        $html = $this->actingAs($this->usuario)->get(route('salidas.create', ['producto' => $this->aceite->id, 'motivo' => 'vencido']))->getContent();
        $this->assertTrue($elegido($html, 'vencido'));
        $this->assertFalse($elegido($html, 'venta_entrega'));

        // Un motivo que no es de salida se ignora.
        $html = $this->actingAs($this->usuario)->get(route('salidas.create', ['producto' => $this->aceite->id, 'motivo' => 'compra']))->getContent();
        $this->assertTrue($elegido($html, 'venta_entrega'));
        $this->assertFalse($elegido($html, 'vencido'));
    }

    public function test_el_menu_muestra_el_contador_de_alertas(): void
    {
        $this->actingAs($this->usuario)->get(route('inventario.index'))
            ->assertSee('title="Lotes vencidos o próximos a caducar">2<span class="visually-hidden"> lotes en alerta</span></span>', false);
    }

    public function test_la_ficha_de_inventario_muestra_el_estado_y_los_dias(): void
    {
        $this->actingAs($this->usuario)->get(route('inventario.show', $this->aceite))
            ->assertSeeInOrder(['VIEJO', 'Vencido', 'Venció hace 10 días', 'PRONTO', 'Próximo a caducar', 'Faltan 11 días', 'LEJANO', 'Vigente']);
    }

    public function test_el_enlace_de_configuracion_solo_lo_ve_el_administrador(): void
    {
        $this->actingAs($this->usuario)->get(route('caducidad.index'))->assertDontSee('Cambiar el periodo');
        $this->actingAs(User::factory()->admin()->create())->get(route('caducidad.index'))
            ->assertSee('Cambiar el periodo')
            ->assertSee(route('configuracion.edit'), false);
    }
}
