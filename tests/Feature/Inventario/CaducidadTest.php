<?php

namespace Tests\Feature\Inventario;

use App\Enums\EstadoCaducidad;
use App\Enums\MotivoMovimiento;
use App\Models\Lote;
use App\Models\User;
use App\Services\ConfiguracionService;
use App\Services\ExistenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clasificación de caducidad y alertas (Requerimientos RF-08). Hoy es el
 * 30-10-2026 y el periodo de alerta inicial es de 30 días.
 */
class CaducidadTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private ExistenciasService $existencias;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->existencias = new ExistenciasService;
    }

    /**
     * @return list<string> Códigos de los lotes en alerta, en orden.
     */
    private function enAlerta(int $dias = 30, array $estados = [EstadoCaducidad::Vencido, EstadoCaducidad::Proximo]): array
    {
        return $this->existencias->consultaCaducidad($dias, $estados)->pluck('codigo_lote')->all();
    }

    public function test_limites_de_cada_estado(): void
    {
        $casos = [
            'AYER' => ['2026-10-29', -1, EstadoCaducidad::Vencido],
            'HOY' => ['2026-10-30', 0, EstadoCaducidad::Proximo],
            'N' => ['2026-11-29', 30, EstadoCaducidad::Proximo],
            'N+1' => ['2026-11-30', 31, EstadoCaducidad::Vigente],
        ];

        foreach ($casos as $codigo => [$caducidad, $dias, $estado]) {
            $lote = $this->recibir($this->botella, 1, $codigo, $caducidad);

            $this->assertSame($dias, $this->existencias->diasRestantes($lote), $codigo);
            $this->assertSame($estado, $this->existencias->estado($lote, 30), $codigo);
        }

        $this->assertSame(['AYER'], $this->enAlerta(estados: [EstadoCaducidad::Vencido]));
        $this->assertSame(['HOY', 'N'], $this->enAlerta(estados: [EstadoCaducidad::Proximo]));
        $this->assertSame(['N+1'], $this->enAlerta(estados: [EstadoCaducidad::Vigente]));
    }

    /** Caso 6. */
    public function test_un_lote_dentro_de_los_proximos_30_dias_aparece_en_las_alertas_con_fecha_y_cantidad(): void
    {
        $this->recibir($this->caja, 2, 'L1', '2026-11-15');

        $alerta = $this->existencias->consultaCaducidad(30, [EstadoCaducidad::Proximo])->sole();

        $this->assertSame('L1', $alerta->codigo_lote);
        $this->assertSame('2026-11-15', $alerta->fecha_caducidad->toDateString());
        $this->assertSame(24, $alerta->existencia_base);
        $this->assertSame(16, $alerta->dias_restantes);
    }

    /** Caso 7. */
    public function test_un_lote_vencido_se_clasifica_aparte(): void
    {
        $this->recibir($this->botella, 3, 'VIEJO', '2026-10-01');
        $this->recibir($this->botella, 3, 'NUEVO', '2026-11-10');

        $this->assertSame(['VIEJO'], $this->enAlerta(estados: [EstadoCaducidad::Vencido]));
        $this->assertSame(['NUEVO'], $this->enAlerta(estados: [EstadoCaducidad::Proximo]));
        $this->assertSame(['vencidos' => 1, 'proximos' => 1], $this->existencias->resumenCaducidad(30));
    }

    public function test_sin_caducidad_o_agotados_no_generan_alertas(): void
    {
        $fosforos = $this->crearProducto('Fósforos');
        $fosforos->update(['controla_caducidad' => false]);
        $this->inventario->registrarEntrada($this->usuario, $this->token(), $fosforos, now(), MotivoMovimiento::Compra,
            [['presentacion_id' => $fosforos->presentacionBase->id, 'cantidad' => 5]]);

        $agotado = $this->recibir($this->botella, 2, 'AGOTADO', '2026-10-01');
        $this->inventario->registrarSalida($this->usuario, $this->token(), $this->aceite, now(), MotivoMovimiento::Vencido,
            [['lote_id' => $agotado->id, 'presentacion_id' => $this->botella->id, 'cantidad' => 2]]);

        $this->assertSame([], $this->enAlerta(estados: EstadoCaducidad::cases()));
        $this->assertNull($this->existencias->estado(Lote::where('producto_id', $fosforos->id)->sole(), 30));
    }

    public function test_cambiar_el_periodo_reclasifica_al_instante(): void
    {
        $this->recibir($this->botella, 1, 'L20', '2026-11-19');
        $configuracion = new ConfiguracionService;

        $this->assertSame(['L20'], $this->enAlerta($configuracion->diasAlertaCaducidad()));

        $configuracion->cambiarDiasAlertaCaducidad(User::factory()->admin()->create(), 10);

        $this->assertSame([], $this->enAlerta($configuracion->diasAlertaCaducidad()));
        $this->assertSame(EstadoCaducidad::Vigente, $this->existencias->estado(Lote::sole(), $configuracion->diasAlertaCaducidad()));
    }

    public function test_textos_de_dias_restantes(): void
    {
        $this->assertSame('Vence hoy', EstadoCaducidad::textoDias(0));
        $this->assertSame('Falta 1 día', EstadoCaducidad::textoDias(1));
        $this->assertSame('Faltan 12 días', EstadoCaducidad::textoDias(12));
        $this->assertSame('Venció ayer', EstadoCaducidad::textoDias(-1));
        $this->assertSame('Venció hace 3 días', EstadoCaducidad::textoDias(-3));
    }
}
