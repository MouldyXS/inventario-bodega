<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel principal (Requerimientos RF-10, §9.1). Hoy es el 30-10-2026.
 */
class PanelTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
    }

    public function test_panel_vacio(): void
    {
        $this->actingAs($this->usuario)->get(route('inicio'))
            ->assertOk()
            ->assertSee('Todo en orden')
            ->assertSee('No hay lotes vencidos con existencias.')
            ->assertSee('No hay lotes próximos a caducar.')
            ->assertSee('Ningún producto está por debajo de su mínimo.')
            ->assertSee('Todavía no hay movimientos.')
            ->assertDontSee('Etapa 6');
    }

    public function test_panel_con_alertas_minimos_y_movimientos(): void
    {
        $this->aceite->update(['stock_minimo' => 50]);
        $this->recibir($this->botella, 4, 'VIEJO', '2026-10-20');
        $this->recibir($this->caja, 2, 'PRONTO', '2026-11-10');
        $this->recibir($this->caja, 1, 'LEJANO', '2027-06-30');

        $this->actingAs($this->usuario)->get(route('inicio'))
            ->assertOk()
            ->assertSeeInOrder(['Productos registrados', '1', 'Productos con existencia', '1', 'Lotes activos', '3'])
            ->assertSee('Requiere atención')
            ->assertDontSee('Todo en orden')
            ->assertSeeInOrder(['Requiere atención', 'Lotes vencidos', '1', 'Próximos a caducar', '1', 'Bajo el mínimo', '1', 'Productos registrados'])
            ->assertSeeInOrder(['Lotes vencidos', 'VIEJO', 'Venció hace 10 días', 'Próximos a caducar', 'PRONTO', 'Faltan 11 días'])
            // Disponible 36 (sin las 4 vencidas) de un mínimo de 50.
            ->assertSeeInOrder(['Bajo el mínimo', $this->aceite->nombre, '36', 'de 50 Botella'])
            ->assertSee(route('inventario.index', ['bajo_minimo' => 1]))
            ->assertSee(route('caducidad.index', ['estado' => 'vencido']));
    }

    public function test_movimientos_recientes_por_orden_de_registro(): void
    {
        $lote = $this->recibir($this->caja, 10);
        // Registrada después, pero con fecha de ayer (decisión I2): aparece arriba.
        $this->inventario->registrarSalida($this->usuario, $this->token(), $this->aceite, now()->subDay(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'cantidad' => 3]]);
        $this->travel(1)->minutes();

        $this->actingAs($this->usuario)->get(route('inicio'))
            ->assertSeeInOrder(['Movimientos recientes', 'Salida', '-3 Caja x12', 'Ocurrió el 29/10/2026', 'Entrada', '+10 Caja x12']);
    }

    public function test_el_filtro_bajo_minimo_del_inventario(): void
    {
        $this->aceite->update(['stock_minimo' => 50]);
        $fideos = $this->crearProducto('Fideos 500 g');
        $fideos->update(['stock_minimo' => 5]);
        $this->recibir($fideos->presentacionBase, 10, 'F1', '2027-01-31');

        $this->actingAs(User::factory()->create())->get(route('inventario.index', ['bajo_minimo' => 1]))
            ->assertSee($this->aceite->nombre)
            ->assertDontSee('Fideos 500 g');

        $this->actingAs(User::factory()->create())->get(route('inventario.index'))
            ->assertSee('Fideos 500 g');
    }
}
