<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Restricciones del catálogo que dependen del historial (Requerimientos RF-01, RF-02).
 */
class RestriccionesCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoService $catalogo;

    private Producto $aceite;

    private Presentacion $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogo = new CatalogoService;
        $this->aceite = $this->catalogo->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Aceite de girasol 1 L',
        ], 'Botella');
        $this->caja = $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
    }

    private function recibir(Presentacion $presentacion, int $cantidad): void
    {
        (new InventarioService)->registrarEntrada(
            User::factory()->create(), (string) Str::uuid(), $this->aceite, now(), MotivoMovimiento::Compra,
            [['presentacion_id' => $presentacion->id, 'cantidad' => $cantidad, 'fecha_caducidad' => '2027-01-31']],
        );
    }

    private function assertErrorEn(string $campo, callable $operacion): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba un error de validación en $campo.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    public function test_sin_movimientos_el_factor_todavia_puede_corregirse(): void
    {
        $this->catalogo->actualizarPresentacion($this->caja, ['factor' => 24]);

        $this->assertSame(24, $this->caja->fresh()->factor);
    }

    /** Caso 19. */
    public function test_el_factor_no_cambia_si_la_presentacion_tiene_movimientos(): void
    {
        $this->recibir($this->caja, 2);

        $this->assertErrorEn('factor', fn () => $this->catalogo->actualizarPresentacion($this->caja, ['factor' => 24]));
        $this->assertSame(12, $this->caja->fresh()->factor);

        // El nombre sí puede cambiar.
        $this->catalogo->actualizarPresentacion($this->caja, ['nombre' => 'Caja de 12', 'factor' => 12]);
        $this->assertSame('Caja de 12', $this->caja->fresh()->nombre);
    }

    /** Caso 20. */
    public function test_el_control_de_caducidad_no_cambia_si_el_producto_tiene_lotes(): void
    {
        $this->recibir($this->caja, 1);

        $this->assertErrorEn('controla_caducidad', fn () => $this->catalogo->actualizarProducto($this->aceite, ['controla_caducidad' => false]));
        $this->assertTrue($this->aceite->fresh()->controla_caducidad);

        // Enviar el mismo valor no es un cambio.
        $this->catalogo->actualizarProducto($this->aceite, ['controla_caducidad' => true, 'nombre' => 'Aceite de girasol 1 litro']);
        $this->assertSame('Aceite de girasol 1 litro', $this->aceite->fresh()->nombre);
    }

    public function test_sin_lotes_el_control_de_caducidad_todavia_puede_cambiar(): void
    {
        $this->catalogo->actualizarProducto($this->aceite, ['controla_caducidad' => false]);

        $this->assertFalse($this->aceite->fresh()->controla_caducidad);
    }

    public function test_una_presentacion_con_existencias_no_se_desactiva(): void
    {
        $this->recibir($this->caja, 3);

        $this->assertErrorEn('activo', fn () => $this->catalogo->actualizarPresentacion($this->caja, ['activo' => false]));
        $this->assertTrue($this->caja->fresh()->activo);
    }

    public function test_el_cambio_rechazado_tambien_se_informa_por_http(): void
    {
        $this->recibir($this->caja, 1);
        $gestor = User::factory()->conPermiso('puede_gestionar_catalogo')->create();

        $this->actingAs($gestor)
            ->put(route('presentaciones.update', $this->caja), ['nombre' => 'Caja x12', 'factor' => '24', 'activo' => '1'])
            ->assertSessionHasErrors('factor');

        $this->actingAs($gestor)
            ->put(route('productos.update', $this->aceite), [
                'categoria_id' => $this->aceite->categoria_id,
                'nombre' => $this->aceite->nombre,
                'activo' => '1',
            ])
            ->assertSessionHasErrors('controla_caducidad');
    }
}
