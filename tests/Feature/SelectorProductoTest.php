<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Inventario\PreparaInventario;
use Tests\TestCase;

/**
 * Selector de productos de Recibir y Retirar: la lista completa viene impresa por el servidor y el
 * navegador la filtra al escribir. Las reglas de cada operación no cambian: solo se listan productos activos.
 */
class SelectorProductoTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private Producto $frejol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->recibir($this->caja, 2);
        $this->frejol = $this->crearProducto('Fréjol canario 500 g');
    }

    public function test_sin_buscar_se_listan_todos_los_productos_activos_con_su_nombre_completo(): void
    {
        foreach (['recepciones.create', 'salidas.create'] as $ruta) {
            $html = $this->actingAs($this->usuario)->get(route($ruta))->assertOk()->getContent();

            $this->assertStringContainsString('data-selector-producto', $html);
            $this->assertStringContainsString('Aceite de girasol 1 L', $html);
            $this->assertStringContainsString('Fréjol canario 500 g', $html);
            $this->assertStringContainsString('role="search"', $html);
            $this->assertStringContainsString('data-anuncio', $html);
            $this->assertStringContainsString('aria-live="polite"', $html);
            $this->assertStringContainsString('2 productos', $html);
            $this->assertStringContainsString('inputmode="search"', $html);
            $this->assertStringContainsString('autocomplete="off"', $html);
        }
    }

    public function test_los_productos_inactivos_no_se_listan_en_ninguna_operacion(): void
    {
        $this->frejol->update(['activo' => false]);

        foreach (['recepciones.create', 'salidas.create'] as $ruta) {
            $this->actingAs($this->usuario)->get(route($ruta))->assertOk()
                ->assertDontSee('Fréjol canario 500 g')
                ->assertSee('1 producto');
        }
    }

    public function test_cada_fila_lleva_el_texto_de_busqueda_sin_tildes_ni_mayusculas(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('recepciones.create'))->getContent();

        $this->assertStringContainsString('data-busqueda="frejol canario 500 g ', $html);
        $this->assertStringContainsString('data-nombre="frejol canario 500 g"', $html);
    }

    public function test_solo_retirar_muestra_las_existencias_y_marca_los_agotados(): void
    {
        $retirar = $this->actingAs($this->usuario)->get(route('salidas.create'))->assertOk();
        $retirar->assertSee('Existencias:', false)
            ->assertSee('Sin existencias')
            ->assertSee('24 Botella')
            ->assertSee('data-solo-existencias', false)
            ->assertSee('producto-agotado', false);

        $this->actingAs($this->usuario)->get(route('recepciones.create'))->assertOk()
            ->assertDontSee('Existencias:', false)
            ->assertDontSee('Sin existencias')
            ->assertDontSee('data-solo-existencias', false);
    }

    public function test_buscar_sin_javascript_deja_ocultos_los_que_no_coinciden(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('recepciones.create', ['q' => 'canario']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-nombre="frejol canario 500 g"(?![^>]*\shidden)[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/data-nombre="aceite de girasol 1 l"[^>]*\shidden\s*>/', $html);
        $this->assertStringContainsString('1 producto', $html);
    }

    public function test_una_palabra_intermedia_coincide_como_en_el_servidor(): void
    {
        $html = $this->actingAs($this->usuario)->get(route('salidas.create', ['q' => 'girasol']))->getContent();

        $this->assertMatchesRegularExpression('/data-nombre="aceite de girasol 1 l"(?![^>]*\shidden)[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/data-nombre="frejol canario 500 g"[^>]*\shidden\s*>/', $html);
    }

    public function test_sin_coincidencias_se_explica_y_se_puede_quitar_el_filtro(): void
    {
        $this->actingAs($this->usuario)->get(route('salidas.create', ['q' => 'no-existe-xyz']))
            ->assertOk()
            ->assertSee('No hay productos activos que coincidan con «no-existe-xyz».')
            ->assertSee('estado-vacio', false)
            ->assertSee('data-limpiar', false)
            ->assertSee('0 productos');
    }

    public function test_un_codigo_de_barras_sigue_llevando_directo_al_formulario(): void
    {
        $this->caja->codigos()->create(['codigo' => '17861000000014']);

        foreach (['recepciones.create', 'salidas.create'] as $ruta) {
            $this->actingAs($this->usuario)->get(route($ruta, ['q' => '17861000000014']))
                ->assertRedirect(route($ruta, ['producto' => $this->aceite->id, 'presentacion' => $this->caja->id]));
        }
    }

    public function test_con_mas_de_500_productos_solo_se_lista_al_buscar(): void
    {
        $categoria = $this->aceite->categoria_id;
        $filas = [];

        for ($i = 0; $i < 500; $i++) {
            $filas[] = ['categoria_id' => $categoria, 'nombre' => 'Producto de relleno '.$i, 'activo' => true, 'created_at' => now(), 'updated_at' => now()];
        }

        Producto::insert($filas);

        $html = $this->actingAs($this->usuario)->get(route('recepciones.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-selector-producto', $html);
        $this->assertStringNotContainsString('Producto de relleno 1<', $html);
        $this->assertStringContainsString('escribe el nombre o la marca', $html);

        $this->actingAs($this->usuario)->get(route('recepciones.create', ['q' => 'girasol']))->assertOk()
            ->assertSee('Aceite de girasol 1 L')
            ->assertDontSee('data-selector-producto', false);
    }
}
