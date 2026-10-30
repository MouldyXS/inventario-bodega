<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\ResultadoMovimiento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ajustes por conteo físico y corrección de datos de lotes (Requerimientos RF-04, RF-09).
 */
class AjustesTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private User $ajustador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->ajustador = User::factory()->conPermiso('puede_ajustar_inventario')->create();
    }

    /**
     * @param  list<array<string, mixed>>  $conteos
     */
    private function ajustar(array $conteos, ?User $usuario = null, MotivoMovimiento $motivo = MotivoMovimiento::ConteoFisico, ?string $observacion = 'Conteo de fin de mes'): ResultadoMovimiento
    {
        return $this->inventario->registrarAjuste($usuario ?? $this->ajustador, $this->token(), $this->aceite, now(), $motivo, $conteos, $observacion);
    }

    public function test_el_ajuste_registra_la_diferencia_con_lo_contado(): void
    {
        $lote = $this->recibir($this->caja, 10);
        $this->recibir($this->botella, 4);

        $resultado = $this->ajustar([
            ['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 8],
            ['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'contado' => 7],
        ]);

        $this->assertSame([-2, 3], $resultado->movimiento->detalles->pluck('cantidad')->all());
        $this->assertSame(8, $this->existencia($lote, $this->caja));
        $this->assertSame(7, $this->existencia($lote, $this->botella));
    }

    public function test_los_conteos_sin_diferencia_se_ignoran_y_si_no_hay_ninguna_se_avisa(): void
    {
        $lote = $this->recibir($this->caja, 10);
        $sinDiferencia = ['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 10];

        $this->assertErrorEn('conteos', fn () => $this->ajustar([$sinDiferencia]), 'No hay diferencias');

        $resultado = $this->ajustar([$sinDiferencia, ['lote_id' => $lote->id, 'presentacion_id' => $this->botella->id, 'contado' => 2]]);
        $this->assertCount(1, $resultado->movimiento->detalles);
    }

    public function test_contar_cero_deja_el_par_en_cero(): void
    {
        $lote = $this->recibir($this->caja, 3);

        $this->ajustar([['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 0]], motivo: MotivoMovimiento::Danado);

        $this->assertSame(0, $this->existencia($lote, $this->caja));
    }

    public function test_reglas_del_ajuste(): void
    {
        $lote = $this->recibir($this->caja, 10);
        $conteo = ['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 9];

        $this->assertErrorEn('observacion', fn () => $this->ajustar([$conteo], observacion: null));
        $this->assertErrorEn('motivo', fn () => $this->ajustar([$conteo], motivo: MotivoMovimiento::Compra));
        $this->assertErrorEn('conteos.1.contado', fn () => $this->ajustar([$conteo, $conteo]));
        $this->assertErrorEn('conteos.0.contado', fn () => $this->ajustar([[...$conteo, 'contado' => -1]]));
        $this->assertSame(1, Movimiento::count());
    }

    /** Caso 25 (servicio). */
    public function test_el_ajuste_requiere_permiso(): void
    {
        $lote = $this->recibir($this->caja, 10);

        $this->expectException(AuthorizationException::class);
        $this->ajustar([['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 9]], usuario: $this->usuario);
    }

    public function test_el_administrador_puede_ajustar_sin_permiso_especial(): void
    {
        $lote = $this->recibir($this->caja, 10);

        $this->ajustar([['lote_id' => $lote->id, 'presentacion_id' => $this->caja->id, 'contado' => 9]], usuario: User::factory()->admin()->create());

        $this->assertSame(9, $this->existencia($lote, $this->caja));
    }

    // --- Corrección de datos de un lote (RF-04) ---------------------------

    public function test_corregir_codigo_y_caducidad_de_un_lote(): void
    {
        $lote = $this->recibir($this->caja, 10, 'L1', '2027-01-31');

        $this->inventario->corregirLote($this->ajustador, $lote, ' L-001 ', '2027-02-28');

        $lote->refresh();
        $this->assertSame('L-001', $lote->codigo_lote);
        $this->assertSame('2027-02-28', $lote->fecha_caducidad->toDateString());
        $this->assertSame(10, $this->existencia($lote, $this->caja));
    }

    public function test_la_correccion_no_puede_coincidir_con_otro_lote(): void
    {
        $this->recibir($this->caja, 1, 'L1', '2027-01-31');
        $otro = $this->recibir($this->caja, 1, 'L2', '2027-01-31');

        $this->assertErrorEn('codigo_lote', fn () => $this->inventario->corregirLote($this->ajustador, $otro, 'L1', '2027-01-31'), 'Ya existe otro lote');
        $this->assertSame('L2', $otro->fresh()->codigo_lote);
    }

    public function test_la_correccion_respeta_el_control_de_caducidad(): void
    {
        $lote = $this->recibir($this->caja, 1);

        $this->assertErrorEn('fecha_caducidad', fn () => $this->inventario->corregirLote($this->ajustador, $lote, 'L1', null));
        $this->assertErrorEn('fecha_caducidad', fn () => $this->inventario->corregirLote($this->ajustador, $lote, 'L1', '2027-02-30'));
    }

    public function test_la_correccion_requiere_permiso(): void
    {
        $lote = $this->recibir($this->caja, 1);

        $this->expectException(AuthorizationException::class);
        $this->inventario->corregirLote($this->usuario, $lote, 'X', '2027-01-31');
    }
}
