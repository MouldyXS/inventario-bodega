<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\ResultadoMovimiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reversiones (Requerimientos RF-09).
 */
class ReversionesTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private User $revisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->revisor = User::factory()->conPermiso('puede_revertir')->create();
    }

    private function revertir(Movimiento $movimiento, ?User $usuario = null, ?string $token = null, ?string $observacion = 'Se registró por error'): ResultadoMovimiento
    {
        return $this->inventario->revertir($usuario ?? $this->revisor, $token ?? $this->token(), $movimiento, $observacion);
    }

    public function test_revertir_una_entrada_no_consumida(): void
    {
        $lote = $this->recibir($this->caja, 10);
        $entrada = Movimiento::sole();

        $reversion = $this->revertir($entrada)->movimiento->fresh();

        $this->assertSame(TipoMovimiento::Reversion, $reversion->tipo);
        $this->assertSame($entrada->id, $reversion->revierte_movimiento_id);
        $this->assertSame(-10, $reversion->detalles->sole()->cantidad);
        $this->assertSame(12, $reversion->detalles->sole()->factor);
        $this->assertSame(0, $this->existenciaBase($lote));
    }

    /** Caso 17. */
    public function test_un_movimiento_solo_se_revierte_una_vez(): void
    {
        $this->recibir($this->caja, 10);
        $entrada = Movimiento::sole();
        $this->revertir($entrada);

        $this->assertErrorEn('movimiento', fn () => $this->revertir($entrada), 'ya fue revertido');
        $this->assertSame(2, Movimiento::count());
    }

    /** Caso 18. */
    public function test_no_se_revierte_una_entrada_ya_consumida(): void
    {
        $lote = $this->recibir($this->botella, 10);
        $entrada = Movimiento::sole();
        $this->inventario->registrarSalida($this->usuario, $this->token(), $this->aceite, now(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 4]]);

        $this->assertErrorEn('movimiento', fn () => $this->revertir($entrada), 'Solo hay 6 Botella');
        $this->assertSame(6, $this->existencia($lote, $this->botella));
    }

    public function test_una_reversion_no_se_revierte(): void
    {
        $this->recibir($this->caja, 10);
        $reversion = $this->revertir(Movimiento::sole())->movimiento;

        $this->assertErrorEn('movimiento', fn () => $this->revertir($reversion), 'no puede revertirse');
    }

    public function test_revertir_una_salida_y_una_conversion(): void
    {
        $lote = $this->recibir($this->caja, 2);
        $conversion = $this->inventario->registrarConversion($this->usuario, $this->token(), $lote, now(), $this->caja->id, 1, $this->botella->id)->movimiento;
        $salida = $this->inventario->registrarSalida($this->usuario, $this->token(), $this->aceite, now(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 5]])->movimiento;

        $this->revertir($salida);
        $this->assertSame(12, $this->existencia($lote, $this->botella));

        $this->revertir($conversion);
        $this->assertSame(2, $this->existencia($lote, $this->caja));
        $this->assertSame(0, $this->existencia($lote, $this->botella));
    }

    public function test_reglas_de_la_reversion(): void
    {
        $this->recibir($this->caja, 10);
        $entrada = Movimiento::sole();

        $this->assertErrorEn('observacion', fn () => $this->revertir($entrada, observacion: ''));

        $this->expectException(AuthorizationException::class);
        $this->revertir($entrada, usuario: $this->usuario);
    }

    public function test_el_reenvio_de_una_reversion_no_la_rechaza_como_ya_revertida(): void
    {
        $this->recibir($this->caja, 10);
        $entrada = Movimiento::sole();
        $token = $this->token();

        $primera = $this->revertir($entrada, token: $token);
        $segunda = $this->revertir($entrada, token: $token);

        $this->assertTrue($segunda->reenvio);
        $this->assertSame($primera->movimiento->id, $segunda->movimiento->id);
    }
}
