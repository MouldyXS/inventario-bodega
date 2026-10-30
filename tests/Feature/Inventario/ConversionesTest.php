<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Presentacion;
use App\Services\ResultadoMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Conversiones entre presentaciones (Requerimientos RF-05).
 */
class ConversionesTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
    }

    private function convertir(Lote $lote, Presentacion $origen, int $cantidad, Presentacion $destino): ResultadoMovimiento
    {
        return $this->inventario->registrarConversion($this->usuario, $this->token(), $lote, now(), $origen->id, $cantidad, $destino->id);
    }

    /** Caso 2. */
    public function test_abrir_una_caja_y_retirar_7_botellas(): void
    {
        $lote = $this->recibir($this->caja, 10);

        $conversion = $this->convertir($lote, $this->caja, 1, $this->botella);
        $this->inventario->registrarSalida($this->usuario, $this->token(), $this->aceite, now(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 7]]);

        $this->assertSame(TipoMovimiento::Conversion, $conversion->movimiento->tipo);
        $this->assertSame(9, $this->existencia($lote, $this->caja));
        $this->assertSame(5, $this->existencia($lote, $this->botella));
        $this->assertSame(113, $this->existenciaBase($lote));
    }

    /** Caso 8. */
    public function test_reempaquetar_sueltas_en_cajas_no_cambia_el_total(): void
    {
        $lote = $this->recibir($this->botella, 30);

        $resultado = $this->convertir($lote, $this->botella, 24, $this->caja);

        $this->assertSame(6, $this->existencia($lote, $this->botella));
        $this->assertSame(2, $this->existencia($lote, $this->caja));
        $this->assertSame(30, $this->existenciaBase($lote));
        $this->assertSame(0, (int) $resultado->movimiento->detalles()->sum('cantidad_base'));
    }

    /** Caso 23. */
    public function test_una_conversion_que_no_da_un_numero_exacto_se_rechaza(): void
    {
        $lote = $this->recibir($this->botella, 30);

        $this->assertErrorEn('cantidad', fn () => $this->convertir($lote, $this->botella, 13, $this->caja), 'no forman un número exacto');
        $this->assertSame(30, $this->existencia($lote, $this->botella));
    }

    public function test_no_se_convierte_mas_de_lo_que_hay_ni_a_la_misma_presentacion(): void
    {
        $lote = $this->recibir($this->caja, 1);

        $this->assertErrorEn('cantidad', fn () => $this->convertir($lote, $this->caja, 2, $this->botella), 'Solo hay 1 Caja x12');
        $this->assertErrorEn('presentacion_destino_id', fn () => $this->convertir($lote, $this->caja, 1, $this->caja));
        $this->assertErrorEn('cantidad', fn () => $this->convertir($lote, $this->caja, 0, $this->botella));
    }

    public function test_la_conversion_queda_en_el_mismo_lote(): void
    {
        $l1 = $this->recibir($this->caja, 2, 'L1');
        $l2 = $this->recibir($this->caja, 2, 'L2');

        $this->convertir($l1, $this->caja, 1, $this->botella);

        $this->assertSame(12, $this->existencia($l1, $this->botella));
        $this->assertSame(0, $this->existencia($l2, $this->botella));
        $this->assertSame(2, $this->existencia($l2, $this->caja));
    }

    public function test_tres_niveles_fardo_a_paquetes(): void
    {
        $paquete = $this->aceite->presentaciones()->create(['nombre' => 'Paquete x6', 'factor' => 6]);
        $lote = $this->recibir($this->caja, 1);

        $this->convertir($lote, $this->caja, 1, $paquete);

        $this->assertSame(2, $this->existencia($lote, $paquete));
        $this->assertSame(12, $this->existenciaBase($lote));
    }

    public function test_se_permite_convertir_en_un_lote_vencido(): void
    {
        $vencido = $this->recibir($this->caja, 1, 'VIEJO', '2026-10-01');

        $this->convertir($vencido, $this->caja, 1, $this->botella);

        $this->assertSame(12, $this->existencia($vencido, $this->botella));
    }

    public function test_el_destino_desactivado_se_rechaza(): void
    {
        $paquete = $this->aceite->presentaciones()->create(['nombre' => 'Paquete x6', 'factor' => 6, 'activo' => false]);
        $lote = $this->recibir($this->caja, 1);

        $this->assertErrorEn('presentacion_destino_id', fn () => $this->convertir($lote, $this->caja, 1, $paquete));
    }
}
