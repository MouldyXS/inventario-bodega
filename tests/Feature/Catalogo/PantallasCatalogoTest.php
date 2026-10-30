<?php

namespace Tests\Feature\Catalogo;

use App\Models\Categoria;
use App\Models\CodigoBarras;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pantallas del catálogo y permisos comprobados en el servidor (Requerimientos §2.3, §9.2).
 */
class PantallasCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->conPermiso('puede_gestionar_catalogo')->create();
    }

    private function crearProducto(string $nombre = 'Aceite de girasol 1 L', ?string $marca = 'La Favorita'): Producto
    {
        return (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => $nombre,
            'marca' => $marca,
        ], 'Botella');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosProducto(array $cambios = []): array
    {
        return [
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Fideos tallarín 500 g',
            'marca' => 'Don Vittorio',
            'controla_caducidad' => '1',
            'stock_minimo' => '24',
            'nombre_presentacion_base' => 'Paquete',
            ...$cambios,
        ];
    }

    // --- Permisos -----------------------------------------------------------

    public function test_cualquier_usuario_consulta_el_catalogo(): void
    {
        $producto = $this->crearProducto();
        $encargado = User::factory()->create();

        $this->actingAs($encargado)->get(route('productos.index'))->assertOk()->assertSee($producto->nombre);
        $this->actingAs($encargado)->get(route('productos.show', $producto))->assertOk()->assertDontSee('Editar producto');
        $this->actingAs($encargado)->get(route('categorias.index'))->assertOk();
    }

    public function test_un_encargado_sin_permiso_no_puede_modificar_el_catalogo(): void
    {
        $producto = $this->crearProducto();
        $base = $producto->presentacionBase;
        $codigo = $base->codigos()->create(['codigo' => '7861000000017']);
        $encargado = User::factory()->create();

        $peticiones = [
            ['get', route('productos.create')],
            ['post', route('productos.store'), $this->datosProducto()],
            ['get', route('productos.edit', $producto)],
            ['put', route('productos.update', $producto), $this->datosProducto()],
            ['get', route('categorias.create')],
            ['post', route('categorias.store'), ['nombre' => 'Lácteos']],
            ['post', route('presentaciones.store', $producto), ['nombre' => 'Caja', 'factor' => 12]],
            ['put', route('presentaciones.update', $base), ['nombre' => 'Unidad']],
            ['post', route('codigos.store', $base), ['codigo' => '7861000000024']],
            ['delete', route('codigos.destroy', $codigo)],
        ];

        foreach ($peticiones as $peticion) {
            [$metodo, $url] = $peticion;
            $this->actingAs($encargado)->{$metodo}($url, $peticion[2] ?? [])->assertForbidden();
        }

        $this->assertSame(1, Producto::count());
        $this->assertSame(0, Categoria::where('nombre', 'Lácteos')->count());
        $this->assertModelExists($codigo);
    }

    public function test_el_administrador_gestiona_el_catalogo_sin_permiso_especial(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('productos.create'))
            ->assertOk();
    }

    // --- Productos -----------------------------------------------------------

    public function test_crear_producto_crea_su_unidad_base(): void
    {
        $respuesta = $this->actingAs($this->gestor())->post(route('productos.store'), $this->datosProducto());

        $producto = Producto::sole();
        $respuesta->assertRedirect(route('productos.show', $producto));
        $this->assertSame('Fideos tallarín 500 g', $producto->nombre);
        $this->assertSame(24, $producto->stock_minimo);
        $this->assertSame('Paquete', $producto->presentacionBase->nombre);
        $this->assertSame(1, $producto->presentacionBase->factor);
    }

    public function test_un_posible_duplicado_requiere_confirmacion(): void
    {
        $existente = $this->crearProducto('Aceite de girasol 1 L', 'La Favorita');
        $gestor = $this->gestor();
        $datos = $this->datosProducto(['nombre' => 'ACEITE  de girasol 1 l', 'marca' => 'la favorita']);

        // Sin confirmar (o un doble envío): no se crea y se avisa.
        $this->actingAs($gestor)->from(route('productos.create'))->post(route('productos.store'), $datos)
            ->assertRedirect(route('productos.create'))
            ->assertSessionHas('duplicados', fn (array $d) => $d[0]['id'] === $existente->id);
        $this->assertSame(1, Producto::count());

        // Con confirmación explícita: se crea.
        $this->actingAs($gestor)->post(route('productos.store'), [...$datos, 'confirmar_duplicado' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Producto::count());
    }

    public function test_editar_producto_y_desactivarlo(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gestor())->put(route('productos.update', $producto), [
            'categoria_id' => $producto->categoria_id,
            'nombre' => 'Aceite de girasol 1 L',
            'marca' => '',
            'stock_minimo' => '',
        ])->assertRedirect(route('productos.show', $producto));

        $producto->refresh();
        $this->assertNull($producto->marca);
        $this->assertNull($producto->stock_minimo);
        $this->assertFalse($producto->activo);
        $this->assertFalse($producto->controla_caducidad);
    }

    public function test_stock_minimo_negativo_no_se_acepta(): void
    {
        $this->actingAs($this->gestor())->post(route('productos.store'), $this->datosProducto(['stock_minimo' => '-1']))
            ->assertSessionHasErrors('stock_minimo');

        $this->assertSame(0, Producto::count());
    }

    public function test_buscar_por_codigo_de_barras(): void
    {
        $aceite = $this->crearProducto('Aceite de girasol 1 L');
        $fideos = $this->crearProducto('Fideos tallarín 500 g', 'Don Vittorio');
        $aceite->presentacionBase->codigos()->create(['codigo' => '7861000000017']);

        $this->actingAs(User::factory()->create())
            ->get(route('productos.index', ['q' => '7861000000017']))
            ->assertOk()
            ->assertSee($aceite->nombre)
            ->assertDontSee($fideos->nombre);
    }

    // --- Presentaciones -------------------------------------------------------

    public function test_anadir_presentacion_con_su_factor(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gestor())
            ->post(route('presentaciones.store', $producto), ['nombre' => 'Caja x12', 'factor' => '12'])
            ->assertRedirect(route('productos.show', $producto));

        $caja = $producto->presentaciones()->where('nombre', 'Caja x12')->sole();
        $this->assertSame(12, $caja->factor);
        $this->assertFalse($caja->es_base);
    }

    public function test_no_se_repite_el_nombre_de_presentacion_en_el_producto(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gestor())
            ->post(route('presentaciones.store', $producto), ['nombre' => 'Botella', 'factor' => '1'])
            ->assertSessionHasErrorsIn('presentacion', 'nombre');
    }

    public function test_no_se_puede_enviar_es_base_al_crear_una_presentacion(): void
    {
        $producto = $this->crearProducto();

        $this->actingAs($this->gestor())
            ->post(route('presentaciones.store', $producto), ['nombre' => 'Unidad', 'factor' => '1', 'es_base' => '1']);

        $this->assertSame(1, $producto->presentaciones()->where('es_base', true)->count());
    }

    public function test_la_base_no_cambia_de_factor_ni_se_desactiva_por_http(): void
    {
        $base = $this->crearProducto()->presentacionBase;
        $gestor = $this->gestor();

        $this->actingAs($gestor)->put(route('presentaciones.update', $base), ['nombre' => 'Botella', 'factor' => '12'])
            ->assertSessionHasErrors('factor');
        $this->actingAs($gestor)->put(route('presentaciones.update', $base), ['nombre' => 'Botella', 'activo' => '0'])
            ->assertSessionHasErrors('activo');

        $base->refresh();
        $this->assertSame(1, $base->factor);
        $this->assertTrue($base->activo);

        $this->actingAs($gestor)->put(route('presentaciones.update', $base), ['nombre' => 'Unidad'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Unidad', $base->fresh()->nombre);
    }

    public function test_desactivar_una_presentacion_que_no_es_base(): void
    {
        $producto = $this->crearProducto();
        $caja = $producto->presentaciones()->create(['nombre' => 'Caja', 'factor' => 12]);

        $this->actingAs($this->gestor())
            ->put(route('presentaciones.update', $caja), ['nombre' => 'Caja', 'factor' => '12', 'activo' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($caja->fresh()->activo);
    }

    // --- Códigos de barras ----------------------------------------------------

    public function test_anadir_y_quitar_codigos_de_barras(): void
    {
        $base = $this->crearProducto()->presentacionBase;
        $gestor = $this->gestor();

        $this->actingAs($gestor)->post(route('codigos.store', $base), ['codigo' => ' 7861000000017 '])
            ->assertSessionHasNoErrors();
        $codigo = CodigoBarras::sole();
        $this->assertSame('7861000000017', $codigo->codigo);

        $this->actingAs($gestor)->delete(route('codigos.destroy', $codigo));
        $this->assertModelMissing($codigo);
    }

    public function test_un_codigo_no_puede_repetirse_en_otra_presentacion(): void
    {
        $aceite = $this->crearProducto('Aceite de girasol 1 L');
        $fideos = $this->crearProducto('Fideos tallarín 500 g', 'Don Vittorio');
        $aceite->presentacionBase->codigos()->create(['codigo' => '7861000000017']);
        $baseFideos = $fideos->presentacionBase;

        $this->actingAs($this->gestor())
            ->post(route('codigos.store', $baseFideos), ['codigo' => '7861000000017'])
            ->assertSessionHasErrorsIn('codigo_'.$baseFideos->id, 'codigo');

        $this->assertSame(1, CodigoBarras::count());
    }

    // --- Categorías -----------------------------------------------------------

    public function test_crear_y_renombrar_categoria(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor)->post(route('categorias.store'), ['nombre' => 'Lácteos'])
            ->assertRedirect(route('categorias.index'));
        $categoria = Categoria::sole();

        $this->actingAs($gestor)->put(route('categorias.update', $categoria), ['nombre' => 'Lácteos y derivados'])
            ->assertRedirect(route('categorias.index'));
        $this->assertSame('Lácteos y derivados', $categoria->fresh()->nombre);

        $this->actingAs($gestor)->post(route('categorias.store'), ['nombre' => 'Lácteos y derivados'])
            ->assertSessionHasErrors('nombre');
    }
}
