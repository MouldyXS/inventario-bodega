<?php

namespace Tests\Feature\Inventario;

use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantallas de salidas, conversiones, ajustes, corrección de lotes, reversiones
 * e historial, con sus permisos comprobados en el servidor (§2.3, §9).
 */
class PantallasMovimientosTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private Lote $lote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->caja->codigos()->create(['codigo' => '17861000000014']);
        $this->lote = $this->recibir($this->caja, 10, 'L1', '2027-01-31');
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function salida(array $cambios = []): array
    {
        return [
            'producto_id' => $this->aceite->id,
            'token' => $this->token(),
            'fecha' => '2026-10-30T09:00',
            'motivo' => 'venta_entrega',
            'lineas' => [['lote_id' => $this->lote->id, 'presentacion_id' => $this->caja->id, 'cantidad' => '2']],
            ...$cambios,
        ];
    }

    // --- Salidas -------------------------------------------------------------

    public function test_escanear_un_codigo_en_salidas_lleva_al_formulario(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('salidas.create', ['q' => '17861000000014']))
            ->assertRedirect(route('salidas.create', ['producto' => $this->aceite->id, 'presentacion' => $this->caja->id]));
    }

    public function test_el_formulario_de_salida_marca_el_lote_recomendado_sin_elegirlo(): void
    {
        $this->recibir($this->caja, 1, 'VIEJO', '2026-10-01');
        $this->recibir($this->caja, 1, 'L0', '2026-12-31');

        $this->actingAs($this->usuario)
            ->get(route('salidas.create', ['producto' => $this->aceite->id]))
            ->assertOk()
            // FEFO: vencido, el más próximo vigente (recomendado) y después L1.
            ->assertSeeInOrder(['VIEJO', 'Vencido', 'L0', 'Recomendado', 'L1'])
            ->assertSee('Elige el lote…')
            ->assertSee('data-vencido', false);
    }

    public function test_registrar_una_salida_ignorando_lineas_sin_cantidad(): void
    {
        $datos = $this->salida();
        $datos['lineas'][] = ['lote_id' => '', 'presentacion_id' => $this->caja->id, 'cantidad' => ''];

        $respuesta = $this->actingAs($this->usuario)->post(route('salidas.store'), $datos);

        $salida = Movimiento::where('tipo', TipoMovimiento::Salida)->sole();
        $respuesta->assertRedirect(route('movimientos.show', $salida))->assertSessionHas('status', 'Salida registrada.');
        $this->assertSame(8, $this->existencia($this->lote, $this->caja));
    }

    public function test_una_salida_sin_existencias_suficientes_muestra_cuanto_hay(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('salidas.store'), $this->salida(['lineas' => [['lote_id' => $this->lote->id, 'presentacion_id' => $this->botella->id, 'cantidad' => '3']]]))
            ->assertSessionHasErrors(['lineas.0.cantidad' => 'Solo hay 0 Botella en el lote L1.']);
    }

    // --- Conversiones ----------------------------------------------------------

    public function test_abrir_una_caja_desde_la_salida_y_volver(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('conversiones.create', ['lote' => $this->lote, 'volver' => 'salida']))
            ->assertOk()
            ->assertSee('name="volver_a_salida"', false);

        $this->actingAs($this->usuario)->post(route('conversiones.store', $this->lote), [
            'token' => $this->token(),
            'presentacion_origen_id' => $this->caja->id,
            'cantidad' => '1',
            'presentacion_destino_id' => $this->botella->id,
            'volver_a_salida' => '1',
        ])->assertRedirect(route('salidas.create', ['producto' => $this->aceite->id]));

        $this->assertSame(9, $this->existencia($this->lote, $this->caja));
        $this->assertSame(12, $this->existencia($this->lote, $this->botella));
    }

    // --- Ajustes y corrección (permiso ajustar-inventario) -------------------

    /** Caso 25 por HTTP. */
    public function test_ajustes_y_correccion_de_lotes_requieren_permiso_en_el_servidor(): void
    {
        $sinPermiso = $this->usuario;
        $conteo = [
            'token' => $this->token(), 'fecha' => '2026-10-30T09:00', 'motivo' => 'conteo_fisico', 'observacion' => 'Conteo',
            'conteos' => [['lote_id' => $this->lote->id, 'presentacion_id' => $this->caja->id, 'contado' => '9']],
        ];

        $this->actingAs($sinPermiso)->get(route('ajustes.create', $this->aceite))->assertForbidden();
        $this->actingAs($sinPermiso)->post(route('ajustes.store', $this->aceite), $conteo)->assertForbidden();
        $this->actingAs($sinPermiso)->get(route('lotes.edit', $this->lote))->assertForbidden();
        $this->actingAs($sinPermiso)->put(route('lotes.update', $this->lote), ['codigo_lote' => 'X', 'fecha_caducidad' => '2027-01-31'])->assertForbidden();
        $this->assertSame(10, $this->existencia($this->lote, $this->caja));

        $sinPermiso->puede_ajustar_inventario = true;
        $sinPermiso->save();

        $this->actingAs($sinPermiso)->post(route('ajustes.store', $this->aceite), $conteo)->assertSessionHasNoErrors();
        $this->assertSame(9, $this->existencia($this->lote, $this->caja));
    }

    public function test_el_ajuste_ignora_las_filas_vacias_y_conserva_lo_escrito_si_falta_algo(): void
    {
        $ajustador = User::factory()->conPermiso('puede_ajustar_inventario')->create();
        $datos = [
            'token' => $this->token(), 'fecha' => '2026-10-30T09:00', 'motivo' => 'conteo_fisico', 'observacion' => '',
            'conteos' => [
                ['lote_id' => $this->lote->id, 'presentacion_id' => $this->botella->id, 'contado' => ''],
                ['lote_id' => $this->lote->id, 'presentacion_id' => $this->caja->id, 'contado' => '7'],
            ],
        ];

        $this->actingAs($ajustador)->post(route('ajustes.store', $this->aceite), $datos)
            ->assertSessionHasErrors('observacion')
            ->assertSessionHasInput('conteos.1.contado', '7');

        $this->actingAs($ajustador)->post(route('ajustes.store', $this->aceite), [...$datos, 'observacion' => 'Conteo semanal'])
            ->assertSessionHasNoErrors();
        $this->assertSame(7, $this->existencia($this->lote, $this->caja));

        $vacio = $datos;
        $vacio['observacion'] = 'Nada';
        $vacio['conteos'][1]['contado'] = '';
        $this->actingAs($ajustador)->post(route('ajustes.store', $this->aceite), $vacio)->assertSessionHasErrors('conteos');
    }

    public function test_corregir_un_lote_por_http(): void
    {
        $ajustador = User::factory()->conPermiso('puede_ajustar_inventario')->create();

        $this->actingAs($ajustador)
            ->put(route('lotes.update', $this->lote), ['codigo_lote' => 'L-01', 'fecha_caducidad' => '2027-02-28'])
            ->assertRedirect(route('inventario.show', $this->aceite));

        $this->assertSame('L-01', $this->lote->fresh()->codigo_lote);
    }

    // --- Reversiones (permiso revertir) --------------------------------------

    public function test_revertir_por_http_requiere_permiso_y_solo_una_vez(): void
    {
        $entrada = Movimiento::sole();
        $datos = fn () => ['token' => $this->token(), 'observacion' => 'Cantidad equivocada'];

        $this->actingAs($this->usuario)->post(route('reversiones.store', $entrada), $datos())->assertForbidden();
        $this->actingAs($this->usuario)->get(route('movimientos.show', $entrada))->assertDontSee('Revertir este movimiento');

        $revisor = User::factory()->conPermiso('puede_revertir')->create();
        $this->actingAs($revisor)->get(route('movimientos.show', $entrada))->assertSee('Revertir este movimiento');

        $this->actingAs($revisor)->post(route('reversiones.store', $entrada), $datos())->assertSessionHas('status', 'Movimiento revertido.');
        $reversion = Movimiento::where('tipo', TipoMovimiento::Reversion)->sole();
        $this->assertSame(0, $this->existencia($this->lote, $this->caja));

        $this->actingAs($revisor)->get(route('movimientos.show', $entrada))
            ->assertSee('Revertido por')
            ->assertDontSee('Revertir este movimiento');
        $this->actingAs($revisor)->get(route('movimientos.show', $reversion))->assertSee('Revierte a');

        $this->actingAs($revisor)->post(route('reversiones.store', $entrada), $datos())
            ->assertSessionHasErrors(['movimiento' => 'Este movimiento ya fue revertido.']);
    }

    // --- Historial -------------------------------------------------------------

    public function test_historial_con_filtros(): void
    {
        // Misma hora que la entrada: el historial ordena por fecha y, a igual fecha, por número.
        $this->actingAs($this->usuario)->post(route('salidas.store'), $this->salida(['fecha' => '2026-10-30T10:00']));
        $entrada = 'Entrada #'.Movimiento::where('tipo', TipoMovimiento::Entrada)->value('id');
        $salida = 'Salida #'.Movimiento::where('tipo', TipoMovimiento::Salida)->value('id');

        $this->actingAs($this->usuario)->get(route('historial.index'))
            ->assertOk()
            ->assertSeeInOrder([$salida, '-2', 'Venta o entrega', $entrada, '+10', 'Compra']);

        $this->actingAs($this->usuario)->get(route('historial.index', ['tipo' => 'salida']))
            ->assertSee($salida)
            ->assertDontSee($entrada);

        $this->actingAs($this->usuario)->get(route('historial.index', ['q' => 'fideos']))
            ->assertSee('No hay movimientos que coincidan.');

        $this->actingAs($this->usuario)->get(route('historial.index', ['desde' => '2026-10-31', 'hasta' => 'no-es-fecha']))
            ->assertOk()
            ->assertSee('No hay movimientos que coincidan.');
    }

    public function test_la_ficha_de_inventario_muestra_las_acciones_segun_el_permiso(): void
    {
        $this->actingAs($this->usuario)->get(route('inventario.show', $this->aceite))
            ->assertSee('Abrir / convertir')
            ->assertSee('Registrar salida')
            ->assertDontSee('Ajuste por conteo')
            ->assertDontSee('>Corregir<', false);

        $this->actingAs(User::factory()->admin()->create())->get(route('inventario.show', $this->aceite))
            ->assertSee('Ajuste por conteo')
            ->assertSee('Corregir');
    }
}
