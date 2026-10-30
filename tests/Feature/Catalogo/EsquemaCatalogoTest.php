<?php

namespace Tests\Feature\Catalogo;

use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restricciones del catálogo que garantiza la base de datos (Requerimientos §5.2 y §5.7).
 * Se insertan filas directamente para comprobar MySQL, no la aplicación.
 */
class EsquemaCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function insertarPresentacion(int $productoId, string $nombre, int $factor, bool $esBase = false): int
    {
        return DB::table('presentaciones')->insertGetId([
            'producto_id' => $productoId,
            'nombre' => $nombre,
            'factor' => $factor,
            'es_base' => $esBase,
            'activo' => true,
        ]);
    }

    private function assertRechazado(callable $operacion): void
    {
        try {
            $operacion();
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('La base de datos aceptó una fila que debía rechazar.');
    }

    public function test_un_producto_admite_como_maximo_una_presentacion_base(): void
    {
        $producto = Producto::factory()->create();
        $this->insertarPresentacion($producto->id, 'Botella', 1, esBase: true);

        $this->assertRechazado(fn () => $this->insertarPresentacion($producto->id, 'Unidad', 1, esBase: true));
    }

    public function test_productos_distintos_tienen_cada_uno_su_base(): void
    {
        $this->insertarPresentacion(Producto::factory()->create()->id, 'Botella', 1, esBase: true);
        $this->insertarPresentacion(Producto::factory()->create()->id, 'Botella', 1, esBase: true);

        $this->assertSame(2, DB::table('presentaciones')->where('es_base', true)->count());
    }

    public function test_la_presentacion_base_debe_tener_factor_1(): void
    {
        $producto = Producto::factory()->create();

        $this->assertRechazado(fn () => $this->insertarPresentacion($producto->id, 'Caja', 12, esBase: true));
    }

    public function test_el_factor_debe_ser_al_menos_1(): void
    {
        $producto = Producto::factory()->create();

        $this->assertRechazado(fn () => $this->insertarPresentacion($producto->id, 'Nada', 0));
        $this->assertRechazado(fn () => $this->insertarPresentacion($producto->id, 'Negativa', -12));
    }

    public function test_el_nombre_de_presentacion_es_unico_dentro_del_producto(): void
    {
        $producto = Producto::factory()->create();
        $this->insertarPresentacion($producto->id, 'Caja', 12);

        $this->assertRechazado(fn () => $this->insertarPresentacion($producto->id, 'Caja', 24));

        // El mismo nombre en otro producto sí es válido.
        $this->insertarPresentacion(Producto::factory()->create()->id, 'Caja', 12);
        $this->assertSame(2, DB::table('presentaciones')->where('nombre', 'Caja')->count());
    }

    public function test_el_stock_minimo_no_puede_ser_negativo(): void
    {
        $this->assertRechazado(fn () => Producto::factory()->create(['stock_minimo' => -1]));
    }

    public function test_el_codigo_de_barras_es_unico_en_todo_el_catalogo(): void
    {
        $unidad = $this->insertarPresentacion(Producto::factory()->create()->id, 'Unidad', 1, esBase: true);
        $otra = $this->insertarPresentacion(Producto::factory()->create()->id, 'Unidad', 1, esBase: true);
        DB::table('codigos_barras')->insert(['presentacion_id' => $unidad, 'codigo' => '7861234567890']);

        $this->assertRechazado(fn () => DB::table('codigos_barras')->insert(['presentacion_id' => $otra, 'codigo' => '7861234567890']));
    }

    public function test_no_se_puede_borrar_un_producto_con_presentaciones(): void
    {
        $producto = Producto::factory()->create();
        $this->insertarPresentacion($producto->id, 'Botella', 1, esBase: true);

        $this->assertRechazado(fn () => DB::table('productos')->where('id', $producto->id)->delete());
    }

    public function test_no_se_puede_borrar_una_categoria_con_productos(): void
    {
        $producto = Producto::factory()->create();

        $this->assertRechazado(fn () => DB::table('categorias')->where('id', $producto->categoria_id)->delete());
    }
}
