<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Inventario\PreparaInventario;
use Tests\TestCase;

/**
 * Listados que se apilan como tarjetas en pantallas estrechas (rediseño, etapa 5):
 * se conserva la semántica de tabla, cada celda lleva su etiqueta y los filtros
 * secundarios se pliegan. El aspecto lo da el CSS; aquí se comprueba el marcado.
 */
class ListadosAccesiblesTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create(['nombre' => 'Aceites'])->id,
            'nombre' => 'Aceite de girasol 1 L',
        ], 'Botella');
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function listados(): array
    {
        return [
            'inventario' => ['inventario.index', ['Producto', 'Categoría', 'Disponible', 'Física', 'Unidad']],
            'productos' => ['productos.index', ['Producto', 'Marca', 'Categoría', 'Unidad base', 'Estado']],
            'categorias' => ['categorias.index', ['Nombre', 'Descripción', 'Productos']],
            'usuarios' => ['usuarios.index', ['Nombre', 'Rol y permisos', 'Estado']],
        ];
    }

    /**
     * @param  list<string>  $columnas
     */
    #[DataProvider('listados')]
    public function test_la_tabla_conserva_la_semantica_y_cada_celda_lleva_su_etiqueta(string $ruta, array $columnas): void
    {
        $html = $this->actingAs($this->admin)->get(route($ruta))->assertOk()->getContent();

        $this->assertStringContainsString('class="table', $html);
        $this->assertStringContainsString('tabla-apilable" role="table"', $html);
        $this->assertMatchesRegularExpression('/<caption class="visually-hidden">[^<]+<\/caption>/', $html);
        $this->assertStringContainsString('<thead role="rowgroup">', $html);
        $this->assertStringContainsString('<tbody role="rowgroup">', $html);
        $this->assertStringContainsString('<tr role="row">', $html);

        foreach ($columnas as $columna) {
            $this->assertMatchesRegularExpression('/<th scope="col" role="columnheader"[^>]*>\s*'.preg_quote($columna, '/').'\s*<\/th>/u', $html, "Cabecera «{$columna}»");
        }

        // Las columnas sin texto (acciones) tienen un nombre accesible oculto.
        $this->assertDoesNotMatchRegularExpression('/<th scope="col" role="columnheader"[^>]*>\s*<\/th>/', $html);

        // Cada celda de datos declara su rol y su etiqueta (vacía en la primera, que hace de título).
        preg_match_all('/<td role="cell" data-etiqueta="([^"]*)"/', $html, $celdas);
        $this->assertNotEmpty($celdas[1]);
        foreach (array_slice($columnas, 1) as $columna) {
            $this->assertContains($columna, $celdas[1], "Etiqueta de celda «{$columna}»");
        }
        $this->assertSame('', $celdas[1][0]);
    }

    public function test_el_historial_y_la_caducidad_tambien_son_tablas_apilables(): void
    {
        $this->prepararInventario();
        $this->recibir($this->caja, 2, 'PRONTO', '2026-11-10');

        $historial = $this->actingAs($this->usuario)->get(route('historial.index'))->assertOk()->getContent();
        $caducidad = $this->actingAs($this->usuario)->get(route('caducidad.index'))->assertOk()->getContent();

        foreach ([$historial, $caducidad] as $html) {
            $this->assertStringContainsString('tabla-apilable" role="table"', $html);
            $this->assertStringContainsString('<tr role="row">', $html);
        }

        // Historial: la columna de motivo ya no se oculta en pantallas estrechas (cabe en la tarjeta).
        $this->assertStringNotContainsString('d-lg-table-cell', $historial);
        foreach (['Tipo', 'Producto', 'Lote', 'Cantidad', 'Usuario', 'Motivo / observación'] as $etiqueta) {
            $this->assertStringContainsString('data-etiqueta="'.$etiqueta.'"', $historial);
        }
        foreach (['Lote', 'Caducidad', 'Existencias'] as $etiqueta) {
            $this->assertStringContainsString('data-etiqueta="'.$etiqueta.'"', $caducidad);
        }
    }

    public function test_los_filtros_secundarios_se_pliegan_y_se_abren_si_hay_alguno_activo(): void
    {
        foreach (['inventario.index', 'productos.index'] as $ruta) {
            $html = $this->actingAs($this->admin)->get(route($ruta))->assertOk()->getContent();
            $this->assertStringContainsString('data-bs-target="#filtros-extra"', $html);
            $this->assertStringContainsString('aria-expanded="false"', $html);
            $this->assertStringContainsString('id="filtros-extra"', $html);
            $this->assertDoesNotMatchRegularExpression('/filtros-extra row g-2 collapse show/', $html);

            $html = $this->actingAs($this->admin)->get(route($ruta, ['estado' => 'todos']))->assertOk()->getContent();
            $this->assertStringContainsString('aria-expanded="true"', $html);
            $this->assertMatchesRegularExpression('/filtros-extra row g-2 collapse\s+show/', $html);
        }
    }

    public function test_el_estado_vacio_ofrece_quitar_filtros_solo_si_hay_filtros(): void
    {
        $this->actingAs($this->admin)->get(route('inventario.index', ['q' => 'no-existe']))
            ->assertOk()
            ->assertSee('No hay productos que coincidan.')
            ->assertSee('Quitar filtros')
            ->assertSee('estado-vacio', false);

        $this->actingAs($this->admin)->get(route('historial.index'))
            ->assertOk()
            ->assertSee('No hay movimientos que coincidan.')
            ->assertDontSee('btn btn-outline-primary btn-sm mt-3', false); // el botón del estado vacío (el formulario ya trae su enlace)

        $this->actingAs($this->admin)->get(route('historial.index', ['desde' => '2026-01-01']))
            ->assertSee('btn btn-outline-primary btn-sm mt-3', false);
    }

    public function test_el_inventario_muestra_miniatura_o_marcador_junto_al_nombre(): void
    {
        $this->actingAs($this->admin)->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('miniatura__marcador', false)
            ->assertSee('>A<', false);
    }
}
