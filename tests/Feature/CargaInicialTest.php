<?php

namespace Tests\Feature;

use App\Enums\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\User;
use App\Services\CargaInicialService;
use App\Services\CatalogoService;
use App\Services\ExistenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Carga inicial de existencias desde CSV (Requerimientos Etapa 9).
 */
class CargaInicialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producto $aceite;

    private Producto $arroz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDateTime(2026, 10, 30, 10, 0, 0));
        $this->admin = User::factory()->admin()->create();
        $this->aceite = $this->producto('Aceite de girasol 1 L', true);
        $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
        $this->arroz = $this->producto('Arroz 1 kg', false);
    }

    private function producto(string $nombre, bool $caducidad): Producto
    {
        return (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => $nombre,
            'controla_caducidad' => $caducidad,
        ], 'Unidad');
    }

    private function csv(string ...$filas): string
    {
        return implode("\n", ['producto;presentacion;cantidad;lote;caducidad;costo', ...$filas]);
    }

    public function test_lee_el_archivo_y_normaliza_fechas_y_costos(): void
    {
        $lectura = (new CargaInicialService(new \App\Services\InventarioService))->leer($this->csv(
            'aceite de girasol 1 l;Caja x12;5;L1;31/01/2027;"28,50"',
            'Arroz 1 kg;Unidad;40;;;',
            '',
        ));

        $this->assertSame([], $lectura['errores']);
        $this->assertCount(2, $lectura['filas']);
        $this->assertSame('2027-01-31', $lectura['filas'][0]['fecha_caducidad']);
        $this->assertSame('28.50', $lectura['filas'][0]['costo_unitario']);
        $this->assertNull($lectura['filas'][1]['fecha_caducidad']);
    }

    public function test_informa_todos_los_errores_con_su_numero_de_fila(): void
    {
        $lectura = (new CargaInicialService(new \App\Services\InventarioService))->leer($this->csv(
            'Producto inexistente;Unidad;1;;;',
            'Arroz 1 kg;Sobre;1;;;',
            'Arroz 1 kg;Unidad;0;;;',
            'Arroz 1 kg;Unidad;1;;2027-13-45;',
        ));

        $this->assertSame([], $lectura['filas']);
        $this->assertCount(4, $lectura['errores']);
        $this->assertStringStartsWith('Fila 2:', $lectura['errores'][0]);
        $this->assertStringStartsWith('Fila 5:', $lectura['errores'][3]);
    }

    public function test_falta_una_columna_obligatoria(): void
    {
        $lectura = (new CargaInicialService(new \App\Services\InventarioService))->leer("producto;cantidad\nArroz 1 kg;3");

        $this->assertStringContainsString('presentacion', $lectura['errores'][0]);
    }

    public function test_el_flujo_completo_registra_entradas_de_carga_inicial_con_la_existencia_correcta(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('carga.csv', $this->csv(
            'Aceite de girasol 1 L;Caja x12;10;L1;2027-01-31;30',
            'Aceite de girasol 1 L;Unidad;3;L1;2027-01-31;',
            'Arroz 1 kg;Unidad;40;;;',
        ));

        $this->actingAs($this->admin)->post(route('carga-inicial.revisar'), ['archivo' => $archivo])
            ->assertOk()->assertSee('Todavía no se guardó nada')->assertSee('Arroz 1 kg');
        $this->assertSame(0, Movimiento::count());

        $this->post(route('carga-inicial.store'))->assertRedirect(route('historial.index'));

        $this->assertSame(2, Movimiento::count());
        $this->assertSame(2, Movimiento::where('motivo', MotivoMovimiento::CargaInicial->value)->count());
        $existencias = new ExistenciasService;
        $this->assertSame(123, $existencias->lotes($this->aceite)->sole()->existencia_base);
        $this->assertSame(40, $existencias->lotes($this->arroz)->sole()->existencia_base);
    }

    public function test_si_un_producto_falla_no_se_registra_nada(): void
    {
        // El aceite controla caducidad y la fila no la trae: lo detecta el servicio al registrar.
        $archivo = UploadedFile::fake()->createWithContent('carga.csv', $this->csv(
            'Arroz 1 kg;Unidad;40;;;',
            'Aceite de girasol 1 L;Unidad;3;L1;;',
        ));

        $this->actingAs($this->admin)->post(route('carga-inicial.revisar'), ['archivo' => $archivo])->assertOk();
        $this->post(route('carga-inicial.store'))->assertSessionHasErrors('archivo');

        $this->assertSame(0, Movimiento::count());
        $this->assertSame(0, Lote::count());
    }

    public function test_reenviar_la_misma_carga_no_duplica_existencias(): void
    {
        $servicio = new CargaInicialService(new \App\Services\InventarioService);
        $filas = $servicio->leer($this->csv('Arroz 1 kg;Unidad;40;;;'))['filas'];
        $token = (string) Str::uuid();

        $servicio->registrar($this->admin, $token, $filas, now());
        $servicio->registrar($this->admin, $token, $filas, now());

        $this->assertSame(1, Movimiento::count());
        $this->assertSame(40, (new ExistenciasService)->lotes($this->arroz)->sole()->existencia_base);
    }

    public function test_solo_el_administrador_puede_cargar(): void
    {
        $encargado = User::factory()->conPermiso('puede_gestionar_catalogo')->conPermiso('puede_ajustar_inventario')->create();

        $this->actingAs($encargado)->get(route('carga-inicial.create'))->assertForbidden();
        $this->actingAs($encargado)->get(route('carga-inicial.plantilla'))->assertForbidden();
        $this->actingAs($encargado)->post(route('carga-inicial.store'))->assertForbidden();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        (new CargaInicialService(new \App\Services\InventarioService))->registrar($encargado, (string) Str::uuid(), [], now());
    }

    public function test_la_plantilla_lista_cada_presentacion_activa(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('carga-inicial.plantilla'))->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('producto;presentacion;cantidad;lote;caducidad;costo', $contenido);
        $this->assertStringContainsString('"Aceite de girasol 1 L";"Caja x12"', $contenido);
        $this->assertStringContainsString('"Arroz 1 kg";Unidad', $contenido);
    }

    public function test_repetir_una_carga_existente_pide_confirmacion(): void
    {
        $archivo = fn () => UploadedFile::fake()->createWithContent('c.csv', $this->csv('Arroz 1 kg;Unidad;1;;;'));

        $this->actingAs($this->admin)->post(route('carga-inicial.revisar'), ['archivo' => $archivo()]);
        $this->post(route('carga-inicial.store'));

        $this->post(route('carga-inicial.revisar'), ['archivo' => $archivo()])->assertSee('Ya hay 1 movimientos');
        $this->post(route('carga-inicial.store'))->assertSessionHasErrors('archivo');
        $this->assertSame(1, Movimiento::count());
    }
}
