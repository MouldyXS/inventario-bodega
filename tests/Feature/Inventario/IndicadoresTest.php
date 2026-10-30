<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Services\ExistenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Indicadores del panel principal (Requerimientos RF-10). Hoy es el
 * 30-10-2026; periodo de alerta de 30 días.
 */
class IndicadoresTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
    }

    public function test_sin_datos_todos_los_indicadores_son_cero_salvo_los_productos(): void
    {
        $this->assertSame([
            'productos' => 1,
            'productos_con_existencia' => 0,
            'lotes_activos' => 0,
            'proximos' => 0,
            'vencidos' => 0,
            'bajo_minimo' => 0,
        ], (new ExistenciasService)->indicadores(30));
    }

    public function test_indicadores_con_datos(): void
    {
        // Aceite: mínimo 30 botellas; 24 vigentes + 10 vencidas → disponible 24, bajo mínimo.
        $this->aceite->update(['stock_minimo' => 30]);
        $this->recibir($this->caja, 2, 'L1', '2027-06-30');
        $this->recibir($this->botella, 10, 'VIEJO', '2026-10-01');
        $this->recibir($this->botella, 3, 'PRONTO', '2026-11-05');

        // Fideos: sin mínimo, con un lote agotado (no cuenta como lote activo ni da existencia).
        $fideos = $this->crearProducto('Fideos 500 g');
        $agotado = $this->recibir($fideos->presentacionBase, 5, 'F1', '2027-01-31');
        $this->inventario->registrarSalida($this->usuario, $this->token(), $fideos, now(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $agotado->id, 'presentacion_id' => $fideos->presentacionBase->id, 'cantidad' => 5]]);

        // Producto inactivo con existencia y mínimo: no cuenta en ningún indicador de productos.
        $inactivo = $this->crearProducto('Aceite descontinuado');
        $this->recibir($inactivo->presentacionBase, 1, 'X', '2027-01-31');
        $inactivo->update(['activo' => false, 'stock_minimo' => 100]);

        $this->assertSame([
            'productos' => 2,
            'productos_con_existencia' => 1,
            'lotes_activos' => 4,
            'proximos' => 1,
            'vencidos' => 1,
            'bajo_minimo' => 1,
        ], (new ExistenciasService)->indicadores(30));
    }

    public function test_bajo_minimo_usa_la_existencia_disponible_e_ignora_productos_sin_minimo(): void
    {
        $this->aceite->update(['stock_minimo' => 5]);
        $this->recibir($this->botella, 5, 'L1', '2027-06-30');
        $this->assertSame(0, (new ExistenciasService)->consultaBajoMinimo()->count());

        // Con 10 vencidas más, la existencia física sube pero la disponible sigue en 5.
        $this->recibir($this->botella, 10, 'VIEJO', '2026-10-01');
        $this->aceite->update(['stock_minimo' => 6]);
        $this->assertSame([$this->aceite->id], (new ExistenciasService)->consultaBajoMinimo()->pluck('id')->all());

        $this->aceite->update(['stock_minimo' => null]);
        $this->assertSame(0, (new ExistenciasService)->consultaBajoMinimo()->count());
    }
}
