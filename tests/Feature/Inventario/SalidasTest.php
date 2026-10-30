<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Services\ResultadoMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Salidas (Requerimientos RF-06, RF-07, §5.6).
 */
class SalidasTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function salida(array $lineas, MotivoMovimiento $motivo = MotivoMovimiento::VentaEntrega, ?string $token = null, ?string $observacion = null): ResultadoMovimiento
    {
        return $this->inventario->registrarSalida($this->usuario, $token ?? $this->token(), $this->aceite, now(), $motivo, $lineas, $observacion);
    }

    /** Caso 4. */
    public function test_retirar_3_botellas_de_un_lote(): void
    {
        $lote = $this->recibir($this->botella, 10);

        $resultado = $this->salida([['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 3]]);

        $this->assertSame(7, $this->existencia($lote, $this->botella));
        $movimiento = $resultado->movimiento->fresh();
        $this->assertSame(TipoMovimiento::Salida, $movimiento->tipo);
        $this->assertSame($this->usuario->id, $movimiento->usuario_id);
        $this->assertSame(-3, $movimiento->detalles->sole()->cantidad);
    }

    /** Caso 5. */
    public function test_una_salida_superior_a_la_existencia_se_rechaza_sin_registrar_nada(): void
    {
        $lote = $this->recibir($this->botella, 10);

        $this->assertErrorEn('lineas.0.cantidad', fn () => $this->salida(
            [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 11]],
        ), 'Solo hay 10 Botella en el lote L1');

        $this->assertSame(10, $this->existencia($lote, $this->botella));
        $this->assertSame(1, Movimiento::count());
    }

    /** Caso 16. */
    public function test_el_negativo_se_valida_por_presentacion_aunque_el_total_alcance(): void
    {
        $lote = $this->recibir($this->caja, 9);

        $this->assertErrorEn('lineas.0.cantidad', fn () => $this->salida(
            [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 3]],
        ), 'Solo hay 0 Botella');

        $this->assertSame(108, $this->existenciaBase($lote));
    }

    public function test_varias_lineas_del_mismo_par_se_suman_antes_de_validar(): void
    {
        $lote = $this->recibir($this->botella, 5);
        $linea = ['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 3];

        $this->assertErrorEn('lineas.0.cantidad', fn () => $this->salida([$linea, $linea]));
        $this->assertSame(5, $this->existencia($lote, $this->botella));
    }

    public function test_salida_de_varios_lotes_en_un_movimiento(): void
    {
        $l1 = $this->recibir($this->botella, 5, 'L1', '2027-01-31');
        $l2 = $this->recibir($this->botella, 5, 'L2', '2027-06-30');

        $this->salida([
            ['lote_id' => $l1->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 5],
            ['lote_id' => $l2->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 2],
        ]);

        $this->assertSame(0, $this->existencia($l1, $this->botella));
        $this->assertSame(3, $this->existencia($l2, $this->botella));
    }

    /** Casos 7 y 24. */
    public function test_un_lote_vencido_solo_sale_como_vencido_o_danado(): void
    {
        $vencido = $this->recibir($this->botella, 10, 'VIEJO', '2026-10-29');
        $linea = [['lote_id' => $vencido->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 2]];

        $this->assertErrorEn('lineas.0.lote_id', fn () => $this->salida($linea), 'está vencido');
        $this->assertErrorEn('lineas.0.lote_id', fn () => $this->salida($linea, MotivoMovimiento::DevolucionProveedor));

        $this->salida($linea, MotivoMovimiento::Vencido);
        $this->salida($linea, MotivoMovimiento::Danado);
        $this->assertSame(6, $this->existencia($vencido, $this->botella));
    }

    public function test_el_dia_de_la_caducidad_el_lote_sale_normalmente(): void
    {
        $lote = $this->recibir($this->botella, 10, 'HOY', '2026-10-30');

        $this->salida([['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 1]]);

        $this->assertSame(9, $this->existencia($lote, $this->botella));
    }

    public function test_un_lote_de_otro_producto_se_rechaza(): void
    {
        $fideos = $this->crearProducto('Fideos 500 g');
        $loteFideos = $this->recibir($fideos->presentacionBase, 10);

        $this->assertErrorEn('lineas.0.lote_id', fn () => $this->salida(
            [['lote_id' => $loteFideos->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 1]],
        ));
    }

    public function test_motivos_de_salida(): void
    {
        $lote = $this->recibir($this->botella, 10);
        $linea = [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 1]];

        $this->assertErrorEn('motivo', fn () => $this->salida($linea, MotivoMovimiento::Compra));
        $this->assertErrorEn('observacion', fn () => $this->salida($linea, MotivoMovimiento::Otro));

        $this->salida($linea, MotivoMovimiento::DevolucionProveedor);
        $this->salida($linea, MotivoMovimiento::Otro, observacion: 'Muestra para el cliente');
        $this->assertSame(8, $this->existencia($lote, $this->botella));
    }

    public function test_reenvio_de_una_salida_y_token_de_otra_operacion(): void
    {
        $lote = $this->recibir($this->botella, 10);
        $linea = [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 4]];
        $token = $this->token();

        $this->assertFalse($this->salida($linea, token: $token)->reenvio);
        $this->assertTrue($this->salida($linea, token: $token)->reenvio);
        $this->assertSame(6, $this->existencia($lote, $this->botella));

        // El token de la entrada no sirve para una salida.
        $tokenEntrada = Movimiento::where('tipo', 'entrada')->value('token');
        $this->assertErrorEn('token', fn () => $this->salida($linea, token: $tokenEntrada));
        $this->assertSame(1, Lote::count());
    }
}
