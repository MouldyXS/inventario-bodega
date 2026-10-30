<?php

namespace Tests\Feature\Inventario;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pantallas de recepciones, detalle de movimiento e inventario (Requerimientos §9.3, §9.4).
 */
class PantallasInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $encargado;

    private Producto $aceite;

    private Presentacion $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDateTime(2026, 10, 30, 10, 0, 0));
        // Un encargado sin permisos especiales puede registrar entradas (§2.3).
        $this->encargado = User::factory()->create();
        $this->aceite = (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Aceite de girasol 1 L',
            'marca' => 'La Favorita',
            'stock_minimo' => 200,
        ], 'Botella');
        $this->caja = $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
        $this->caja->codigos()->create(['codigo' => '17861000000014']);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function recepcion(array $cambios = []): array
    {
        return [
            'producto_id' => $this->aceite->id,
            'token' => (string) Str::uuid(),
            'fecha' => '2026-10-30T09:30',
            'motivo' => 'compra',
            'lineas' => [
                ['presentacion_id' => $this->caja->id, 'cantidad' => '10', 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31', 'costo_unitario' => '18.50'],
            ],
            ...$cambios,
        ];
    }

    public function test_buscar_producto_por_nombre(): void
    {
        $this->actingAs($this->encargado)
            ->get(route('recepciones.create', ['q' => 'girasol']))
            ->assertOk()
            ->assertSee(route('recepciones.create', ['producto' => $this->aceite->id]), false);
    }

    public function test_escanear_un_codigo_lleva_al_formulario_con_su_presentacion(): void
    {
        $this->actingAs($this->encargado)
            ->get(route('recepciones.create', ['q' => '17861000000014']))
            ->assertRedirect(route('recepciones.create', ['producto' => $this->aceite->id, 'presentacion' => $this->caja->id]));

        $this->actingAs($this->encargado)
            ->get(route('recepciones.create', ['producto' => $this->aceite->id, 'presentacion' => $this->caja->id]))
            ->assertOk()
            ->assertSee('name="token"', false)
            ->assertSee('Caducidad');
    }

    public function test_un_producto_inactivo_no_admite_recepciones(): void
    {
        $this->aceite->update(['activo' => false]);

        $this->actingAs($this->encargado)->get(route('recepciones.create', ['producto' => $this->aceite->id]))->assertNotFound();
        $this->actingAs($this->encargado)->post(route('recepciones.store'), $this->recepcion())->assertSessionHasErrors('producto_id');
    }

    public function test_registrar_una_recepcion_ignorando_lineas_en_blanco(): void
    {
        $datos = $this->recepcion();
        $datos['lineas'][] = ['presentacion_id' => $this->caja->id, 'cantidad' => '', 'codigo_lote' => '', 'fecha_caducidad' => '', 'costo_unitario' => ''];

        $respuesta = $this->actingAs($this->encargado)->post(route('recepciones.store'), $datos);

        $movimiento = Movimiento::sole();
        $respuesta->assertRedirect(route('movimientos.show', $movimiento))->assertSessionHas('status', 'Recepción registrada.');
        $this->assertSame($this->encargado->id, $movimiento->usuario_id);
        $this->assertSame('2026-10-30 09:30', $movimiento->fecha->format('Y-m-d H:i'));
        $this->assertCount(1, $movimiento->detalles);

        $this->actingAs($this->encargado)->get(route('movimientos.show', $movimiento))
            ->assertOk()
            ->assertSee('L1')
            ->assertSee('+10')
            ->assertSee('$18.50');
    }

    /** Caso 12 por HTTP. */
    public function test_enviar_dos_veces_el_mismo_formulario(): void
    {
        $datos = $this->recepcion();

        $this->actingAs($this->encargado)->post(route('recepciones.store'), $datos);
        $this->actingAs($this->encargado)->post(route('recepciones.store'), $datos)
            ->assertSessionHas('status', 'Esta recepción ya estaba registrada: no se registró de nuevo.');

        $this->assertSame(1, Movimiento::count());
    }

    public function test_un_error_conserva_los_datos_y_el_token(): void
    {
        $datos = $this->recepcion(['lineas' => [['presentacion_id' => $this->caja->id, 'cantidad' => '3', 'codigo_lote' => 'L9']]]);

        $this->actingAs($this->encargado)
            ->from(route('recepciones.create', ['producto' => $this->aceite->id]))
            ->post(route('recepciones.store'), $datos)
            ->assertSessionHasErrors('lineas.0.fecha_caducidad')
            ->assertSessionHasInput('token', $datos['token']);

        $this->assertSame(0, Movimiento::count());
        $this->assertSame(0, Lote::count());
    }

    public function test_la_fecha_no_puede_ser_futura_por_http(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('recepciones.store'), $this->recepcion(['fecha' => '2026-10-30T11:00']))
            ->assertSessionHasErrors('fecha');
    }

    public function test_inventario_muestra_existencias_disponible_fisica_y_lotes(): void
    {
        $this->actingAs($this->encargado)->post(route('recepciones.store'), $this->recepcion());
        // Lote ya vencido: cuenta en la existencia física, no en la disponible.
        $this->actingAs($this->encargado)->post(route('recepciones.store'), $this->recepcion([
            'lineas' => [['presentacion_id' => $this->aceite->presentacionBase->id, 'cantidad' => '5', 'codigo_lote' => 'VIEJO', 'fecha_caducidad' => '2026-10-01']],
        ]));

        $this->actingAs($this->encargado)->get(route('inventario.index'))
            ->assertOk()
            ->assertSeeInOrder([$this->aceite->nombre, 'Bajo mínimo', '120', '125']);

        $this->actingAs($this->encargado)->get(route('inventario.show', $this->aceite))
            ->assertOk()
            ->assertSeeInOrder(['VIEJO', 'Vencido', 'L1', '10 Caja x12', '120']);
    }
}
