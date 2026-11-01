<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Inventario\PreparaInventario;
use Tests\TestCase;

/**
 * Formularios de movimiento (rediseño, etapa 6): cantidad con botones − / +, motivo
 * como botones segmentados y cabecera con miniatura. Los nombres de los campos y los
 * valores enviados no cambian; aquí se comprueba el marcado y que los errores se muestran.
 */
class FormulariosMovimientoTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->recibir($this->caja, 10, 'L1', '2027-01-31');
    }

    /**
     * @param  list<string>  $valores
     */
    private function assertMotivos(string $html, array $valores, string $elegido): void
    {
        preg_match_all('/<input type="radio"[^>]*name="motivo"[^>]*value="([a-z_]+)"[^>]*>/', $html, $radios, PREG_SET_ORDER);

        $this->assertSame($valores, array_map(fn (array $r) => $r[1], $radios));
        $this->assertStringContainsString('<legend class="form-label fs-6">Motivo</legend>', $html);
        // Solo el primero es obligatorio (basta uno para el grupo) y solo el elegido va marcado.
        $this->assertCount(1, array_filter($radios, fn ($r) => str_contains($r[0], ' required')));
        $marcados = array_values(array_filter($radios, fn ($r) => preg_match('/\schecked/', $r[0]) === 1));
        $this->assertCount(1, $marcados);
        $this->assertSame($elegido, $marcados[0][1]);
        $this->assertStringNotContainsString('<select id="motivo"', $html);
    }

    public function test_recepcion_con_motivo_segmentado_cantidad_con_botones_y_cabecera(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('recepciones.create', ['producto' => $this->aceite->id]))->assertOk()->getContent();

        $this->assertMotivos($html, ['compra', 'carga_inicial', 'otro'], 'compra');
        $this->assertStringContainsString('name="lineas[0][cantidad]"', $html);
        $this->assertStringContainsString('data-stepper="-1"', $html);
        $this->assertStringContainsString('data-stepper="1"', $html);
        $this->assertStringContainsString('class="cabecera-producto', $html);
        $this->assertStringContainsString('<h1 class="h3 mb-0">Nueva recepción</h1>', $html);
        $this->assertStringContainsString('miniatura__marcador', $html);
    }

    public function test_el_motivo_elegido_se_conserva_tras_un_error(): void
    {
        $this->actingAs($this->usuario)
            ->from(route('recepciones.create', ['producto' => $this->aceite->id]))
            ->post(route('recepciones.store'), [
                'producto_id' => $this->aceite->id, 'token' => $this->token(), 'fecha' => '2026-10-30T09:00',
                'motivo' => 'otro', 'lineas' => [['presentacion_id' => $this->caja->id, 'cantidad' => '']],
            ])->assertRedirect();

        $html = $this->get(route('recepciones.create', ['producto' => $this->aceite->id]))->getContent();
        $this->assertMotivos($html, ['compra', 'carga_inicial', 'otro'], 'otro');
    }

    public function test_el_error_de_cantidad_se_muestra_junto_a_los_botones(): void
    {
        $this->actingAs($this->usuario)
            ->from(route('recepciones.create', ['producto' => $this->aceite->id]))
            ->followingRedirects()
            ->post(route('recepciones.store'), [
                'producto_id' => $this->aceite->id, 'token' => $this->token(), 'fecha' => '2026-10-30T09:00',
                'motivo' => 'compra', 'lineas' => [['presentacion_id' => $this->caja->id, 'cantidad' => '0', 'codigo_lote' => 'X']],
            ])
            ->assertSee('La cantidad debe ser mayor que cero.')
            ->assertSee('invalid-feedback d-block', false)
            ->assertSee('form-control is-invalid', false);
    }

    public function test_salida_con_motivo_segmentado_y_lotes_apilables(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('salidas.create', ['producto' => $this->aceite->id]))->assertOk()->getContent();

        $this->assertMotivos($html, ['venta_entrega', 'danado', 'vencido', 'devolucion_proveedor', 'otro'], 'venta_entrega');
        $this->assertStringContainsString('name="lineas[0][cantidad]"', $html);
        $this->assertStringContainsString('data-stepper="1"', $html);
        $this->assertStringContainsString('<h1 class="h3 mb-0">Nueva salida</h1>', $html);
        $this->assertStringContainsString('tabla-apilable" role="table"', $html);
        $this->assertStringContainsString('Lotes disponibles, por orden de caducidad', $html);
        $this->assertStringContainsString('Recomendado', $html);
        // La ayuda que bloquea lotes vencidos lee ahora el grupo de radios.
        $this->assertStringContainsString('formulario.elements.motivo.value', $html);
        $this->assertStringContainsString('id="motivo-', $html);
    }

    public function test_ajuste_con_motivo_segmentado_y_contado_con_botones(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('ajustes.create', ['producto' => $this->aceite->id]))->assertOk()->getContent();

        $this->assertMotivos($html, ['conteo_fisico', 'danado', 'otro'], 'conteo_fisico');
        $this->assertStringContainsString('name="conteos[0][contado]"', $html);
        $this->assertMatchesRegularExpression('/<input type="number" id="contado-0" name="conteos\[0\]\[contado\]" min="0"/', $html);
        $this->assertStringContainsString('aria-label="Contado de Caja x12 en el lote L1"', $html);
        $this->assertStringContainsString('Lotes y presentaciones a contar', $html);
        $this->assertStringContainsString('data-etiqueta="Contado"', $html);
    }

    public function test_conversion_con_cantidad_grande_y_cabecera(): void
    {
        $lote = Lote::sole();

        $html = $this->actingAs($this->usuario)->get(route('conversiones.create', $lote))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="number" id="cantidad" name="cantidad" min="1" step="1" inputmode="numeric" value="1"\s+required/', $html);
        $this->assertStringContainsString('stepper stepper--grande', $html);
        $this->assertStringContainsString('<h1 class="h3 mb-0">Abrir o convertir</h1>', $html);
        $this->assertStringContainsString('name="presentacion_origen_id"', $html);
    }

    public function test_el_buscador_muestra_miniatura_resultados_y_estado_vacio(): void
    {
        $this->actingAs($this->usuario)->get(route('recepciones.create', ['q' => 'Aceite']))
            ->assertOk()
            ->assertSee('resultado-producto', false)
            ->assertSee('miniatura__marcador', false)
            ->assertSee('campo-busqueda', false);

        $this->actingAs($this->usuario)->get(route('salidas.create', ['q' => 'no-existe-xyz']))
            ->assertOk()
            ->assertSee('No hay productos activos que coincidan con «no-existe-xyz».')
            ->assertSee('estado-vacio', false);
    }
}
