<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use App\Services\ExistenciasService;
use App\Services\InventarioService;
use App\Services\ResultadoMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Registro de entradas y existencias resultantes (Requerimientos RF-03, RF-04, §5.4–§5.6).
 */
class EntradasTest extends TestCase
{
    use RefreshDatabase;

    private InventarioService $inventario;

    private ExistenciasService $existencias;

    private User $usuario;

    private Producto $aceite;

    private Presentacion $botella;

    private Presentacion $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDateTime(2026, 10, 30, 10, 0, 0));
        $this->inventario = new InventarioService;
        $this->existencias = new ExistenciasService;
        $this->usuario = User::factory()->create();
        $this->aceite = $this->producto('Aceite de girasol 1 L');
        $this->botella = $this->aceite->presentacionBase;
        $this->caja = $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
    }

    private function producto(string $nombre, bool $controlaCaducidad = true): Producto
    {
        return (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => $nombre,
            'controla_caducidad' => $controlaCaducidad,
        ], 'Botella');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function entrada(array $lineas, ?string $token = null, ?User $usuario = null, ?Producto $producto = null, array $opciones = []): ResultadoMovimiento
    {
        return $this->inventario->registrarEntrada(
            $usuario ?? $this->usuario,
            $token ?? (string) Str::uuid(),
            $producto ?? $this->aceite,
            $opciones['fecha'] ?? now(),
            $opciones['motivo'] ?? MotivoMovimiento::Compra,
            $lineas,
            $opciones['observacion'] ?? null,
        );
    }

    /**
     * @return array<int, int> Cantidad por presentacion_id del lote.
     */
    private function existenciaLote(Lote $lote): array
    {
        return $this->existencias->porLoteYPresentacion($lote->producto)
            ->where('lote_id', $lote->id)
            ->pluck('cantidad', 'presentacion_id')
            ->all();
    }

    private function assertErrorDeValidacion(string $campo, callable $operacion): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba un error de validación en $campo.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    /** Caso 1. */
    public function test_recibir_10_cajas_da_10_cajas_0_sueltas_y_120_botellas(): void
    {
        $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 10, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31']]);

        $lote = Lote::sole();
        $this->assertSame([$this->caja->id => 10], $this->existenciaLote($lote));
        $this->assertSame(120, $this->existencias->lotes($this->aceite)->sole()->existencia_base);
    }

    /** Caso 3. */
    public function test_otra_caducidad_crea_un_lote_nuevo_y_conserva_el_anterior(): void
    {
        $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 10, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31']]);
        $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 2, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-06-30']]);

        $lotes = $this->existencias->lotes($this->aceite);
        $this->assertCount(2, $lotes);
        // Orden FEFO: primero la caducidad más próxima.
        $this->assertSame(['2027-01-31', '2027-06-30'], $lotes->map(fn (Lote $l) => $l->fecha_caducidad->toDateString())->all());
        $this->assertSame([120, 24], $lotes->pluck('existencia_base')->all());
    }

    /** Caso 22. */
    public function test_la_misma_combinacion_suma_al_lote_existente(): void
    {
        $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 10, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31']]);
        $this->travel(2)->days();
        $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 5, 'codigo_lote' => ' L1 ', 'fecha_caducidad' => '2027-01-31']]);

        $lote = $this->existencias->lotes($this->aceite)->sole();
        $this->assertSame(180, $lote->existencia_base);
        $this->assertSame('2026-10-30', $lote->primera_recepcion->toDateString());
        $this->assertSame('2026-11-01', $lote->ultima_recepcion->toDateString());
    }

    public function test_recepcion_mixta_de_cajas_y_sueltas_del_mismo_lote(): void
    {
        $resultado = $this->entrada([
            ['presentacion_id' => $this->caja->id, 'cantidad' => 3, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31'],
            ['presentacion_id' => $this->botella->id, 'cantidad' => 5, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31'],
        ]);

        $lote = Lote::sole();
        $this->assertCount(2, $resultado->movimiento->detalles);
        $this->assertEqualsCanonicalizing([$this->caja->id => 3, $this->botella->id => 5], $this->existenciaLote($lote));
        $this->assertSame(41, $this->existencias->lotes($this->aceite)->sole()->existencia_base);
    }

    public function test_el_detalle_copia_el_factor_y_guarda_el_costo_por_presentacion(): void
    {
        $resultado = $this->entrada([['presentacion_id' => $this->caja->id, 'cantidad' => 2, 'fecha_caducidad' => '2027-01-31', 'costo_unitario' => '18.50']]);

        $detalle = $resultado->movimiento->detalles->sole();
        $this->assertSame(12, $detalle->factor);
        $this->assertSame(24, $detalle->fresh()->cantidad_base);
        $this->assertSame('18.5000', $detalle->fresh()->costo_unitario);
        $this->assertSame('', Lote::sole()->codigo_lote);
    }

    /** Caso 12. */
    public function test_un_reenvio_del_mismo_formulario_no_registra_dos_veces(): void
    {
        $token = (string) Str::uuid();
        $linea = [['presentacion_id' => $this->caja->id, 'cantidad' => 10, 'fecha_caducidad' => '2027-01-31']];

        $primero = $this->entrada($linea, $token);
        $segundo = $this->entrada($linea, $token);

        $this->assertFalse($primero->reenvio);
        $this->assertTrue($segundo->reenvio);
        $this->assertSame($primero->movimiento->id, $segundo->movimiento->id);
        $this->assertSame(1, Movimiento::count());
        $this->assertSame(120, $this->existencias->lotes($this->aceite)->sole()->existencia_base);
    }

    /** Caso 13. */
    public function test_un_token_usado_por_otro_usuario_se_rechaza(): void
    {
        $token = (string) Str::uuid();
        $linea = [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31']];
        $this->entrada($linea, $token);

        $this->assertErrorDeValidacion('token', fn () => $this->entrada($linea, $token, User::factory()->create()));
        $this->assertSame(1, Movimiento::count());
    }

    public function test_un_token_que_no_es_uuid_se_rechaza(): void
    {
        $this->assertErrorDeValidacion('token', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31']], 'no-es-un-uuid'));
    }

    public function test_la_caducidad_es_obligatoria_si_el_producto_la_controla(): void
    {
        $this->assertErrorDeValidacion('lineas.0.fecha_caducidad', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => 1]]));

        $this->assertSame(0, Lote::count());
    }

    public function test_un_producto_sin_control_de_caducidad_no_admite_fecha(): void
    {
        $tornillos = $this->producto('Fósforos caja x10', controlaCaducidad: false);
        $base = $tornillos->presentacionBase;

        $this->assertErrorDeValidacion('lineas.0.fecha_caducidad', fn () => $this->entrada(
            [['presentacion_id' => $base->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31']], producto: $tornillos));

        $this->entrada([['presentacion_id' => $base->id, 'cantidad' => 7]], producto: $tornillos);
        $this->assertNull(Lote::sole()->fecha_caducidad);
    }

    public function test_la_fecha_puede_ser_pasada_pero_no_futura(): void
    {
        $linea = [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31']];

        $this->assertErrorDeValidacion('fecha', fn () => $this->entrada($linea, opciones: ['fecha' => now()->addMinute()]));

        $resultado = $this->entrada($linea, opciones: ['fecha' => now()->subDays(3)]);
        $this->assertSame('2026-10-27 10:00:00', $resultado->movimiento->fresh()->fecha->format('Y-m-d H:i:s'));
    }

    public function test_presentacion_de_otro_producto_o_desactivada_se_rechaza_sin_dejar_lotes(): void
    {
        $fideos = $this->producto('Fideos 500 g');
        $inactiva = $this->aceite->presentaciones()->create(['nombre' => 'Caja vieja', 'factor' => 24, 'activo' => false]);
        $valida = ['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'codigo_lote' => 'NUEVO', 'fecha_caducidad' => '2027-01-31'];

        $this->assertErrorDeValidacion('lineas.1.presentacion_id', fn () => $this->entrada([
            $valida, ['presentacion_id' => $fideos->presentacionBase->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31'],
        ]));
        $this->assertErrorDeValidacion('lineas.1.presentacion_id', fn () => $this->entrada([
            $valida, ['presentacion_id' => $inactiva->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31'],
        ]));

        // La transacción se deshizo: tampoco quedó el lote creado para la primera línea.
        $this->assertSame(0, Lote::count());
        $this->assertSame(0, Movimiento::count());
    }

    public function test_motivos_y_observacion(): void
    {
        $linea = [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31']];

        $this->assertErrorDeValidacion('motivo', fn () => $this->entrada($linea, opciones: ['motivo' => MotivoMovimiento::VentaEntrega]));
        $this->assertErrorDeValidacion('observacion', fn () => $this->entrada($linea, opciones: ['motivo' => MotivoMovimiento::Otro]));

        $this->entrada($linea, opciones: ['motivo' => MotivoMovimiento::CargaInicial]);
        $this->assertSame(MotivoMovimiento::CargaInicial, Movimiento::sole()->motivo);
    }

    public function test_cantidades_y_costos_invalidos(): void
    {
        $this->assertErrorDeValidacion('lineas.0.cantidad', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => 0, 'fecha_caducidad' => '2027-01-31']]));
        $this->assertErrorDeValidacion('lineas.0.cantidad', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => '2.5', 'fecha_caducidad' => '2027-01-31']]));
        $this->assertErrorDeValidacion('lineas.0.costo_unitario', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-01-31', 'costo_unitario' => '-1']]));
        $this->assertErrorDeValidacion('lineas.0.fecha_caducidad', fn () => $this->entrada(
            [['presentacion_id' => $this->caja->id, 'cantidad' => 1, 'fecha_caducidad' => '2027-02-30']]));
        $this->assertErrorDeValidacion('lineas', fn () => $this->entrada([]));
    }

    public function test_existencia_fisica_y_disponible_sin_vencidos(): void
    {
        // Un lote ya vencido (ayer) y otro que vence hoy (todavía válido, RF-07).
        $this->entrada([['presentacion_id' => $this->botella->id, 'cantidad' => 4, 'fecha_caducidad' => '2026-10-29']]);
        $this->entrada([['presentacion_id' => $this->botella->id, 'cantidad' => 6, 'fecha_caducidad' => '2026-10-30']]);

        $producto = $this->existencias->consultaProductos()->whereKey($this->aceite->id)->sole();
        $this->assertSame(10, $producto->existencia_fisica);
        $this->assertSame(6, $producto->existencia_disponible);

        [$vencido, $vigente] = $this->existencias->lotes($this->aceite)->all();
        $this->assertTrue($this->existencias->estaVencido($vencido));
        $this->assertFalse($this->existencias->estaVencido($vigente));
    }
}
