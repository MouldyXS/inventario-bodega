<?php

namespace Tests\Feature\Catalogo;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogoServiceTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoService $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogo = new CatalogoService;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crear(array $datos = [], string $nombreBase = 'Botella'): Producto
    {
        return $this->catalogo->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Aceite de girasol 1 L',
            'marca' => 'La Favorita',
            ...$datos,
        ], $nombreBase);
    }

    /** Caso 21. */
    public function test_el_producto_se_crea_con_exactamente_una_presentacion_base_de_factor_1(): void
    {
        $producto = $this->crear();

        $this->assertCount(1, $producto->presentaciones);
        $base = $producto->presentacionBase;
        $this->assertSame('Botella', $base->nombre);
        $this->assertSame(1, $base->factor);
        $this->assertTrue($base->es_base);
    }

    public function test_si_falla_la_presentacion_base_no_queda_el_producto(): void
    {
        try {
            // El nombre de la presentación excede 50 caracteres: MySQL estricto lo rechaza.
            $this->crear(nombreBase: str_repeat('x', 51));
            $this->fail('Se esperaba un error al guardar la presentación base.');
        } catch (QueryException) {
            $this->assertSame(0, Producto::count());
        }
    }

    public function test_detecta_duplicados_sin_distinguir_mayusculas_ni_espacios(): void
    {
        $existente = $this->crear();

        $duplicados = $this->catalogo->posiblesDuplicados('  aceite DE  girasol 1 l ', 'la   favorita');

        $this->assertSame([$existente->id], $duplicados->pluck('id')->all());
    }

    public function test_sin_marca_equivale_a_marca_vacia(): void
    {
        $existente = $this->crear(['marca' => null]);

        $this->assertSame([$existente->id], $this->catalogo->posiblesDuplicados('Aceite de girasol 1 L', '')->pluck('id')->all());
        $this->assertSame([$existente->id], $this->catalogo->posiblesDuplicados('Aceite de girasol 1 L', null)->pluck('id')->all());
    }

    public function test_otra_marca_u_otro_nombre_no_son_duplicados(): void
    {
        $this->crear();

        $this->assertCount(0, $this->catalogo->posiblesDuplicados('Aceite de girasol 1 L', 'Otra marca'));
        $this->assertCount(0, $this->catalogo->posiblesDuplicados('Aceite de girasol 500 ml', 'La Favorita'));
    }

    public function test_la_presentacion_base_solo_cambia_de_nombre(): void
    {
        $base = $this->crear()->presentacionBase;

        $this->catalogo->actualizarPresentacion($base, ['nombre' => 'Unidad', 'factor' => 1, 'activo' => true]);

        $this->assertSame('Unidad', $base->fresh()->nombre);
    }

    public function test_la_presentacion_base_no_cambia_de_factor(): void
    {
        $base = $this->crear()->presentacionBase;

        $this->expectException(ValidationException::class);
        $this->catalogo->actualizarPresentacion($base, ['factor' => 12]);
    }

    public function test_la_presentacion_base_no_puede_desactivarse(): void
    {
        $base = $this->crear()->presentacionBase;

        try {
            $this->catalogo->actualizarPresentacion($base, ['activo' => false]);
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException) {
            $this->assertTrue($base->fresh()->activo);
        }
    }
}
