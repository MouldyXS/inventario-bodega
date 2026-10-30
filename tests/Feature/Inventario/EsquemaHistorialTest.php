<?php

namespace Tests\Feature\Inventario;

use App\Models\Categoria;
use App\Models\DetalleMovimiento;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * Restricciones del historial que garantiza la base de datos (Requerimientos §5.2, §5.7).
 * Se insertan filas directamente para comprobar MySQL, no el servicio.
 */
class EsquemaHistorialTest extends TestCase
{
    use RefreshDatabase;

    private int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuarioId = User::factory()->create()->id;
    }

    /**
     * @return array{producto: int, base: int, caja: int, lote: int}
     */
    private function productoConLote(string $nombre = 'Aceite 1 L'): array
    {
        $producto = (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => $nombre,
        ], 'Botella');
        $caja = $producto->presentaciones()->create(['nombre' => 'Caja', 'factor' => 12]);
        $lote = DB::table('lotes')->insertGetId(['producto_id' => $producto->id, 'codigo_lote' => 'L1', 'fecha_caducidad' => '2027-01-31']);

        return ['producto' => $producto->id, 'base' => $producto->presentacionBase->id, 'caja' => $caja->id, 'lote' => $lote];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function movimiento(array $cambios = []): int
    {
        return DB::table('movimientos')->insertGetId([
            'tipo' => 'entrada',
            'motivo' => 'compra',
            'fecha' => now(),
            'usuario_id' => $this->usuarioId,
            'token' => (string) Str::uuid(),
            ...$cambios,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function detalle(int $movimientoId, array $datos): int
    {
        return DB::table('detalles_movimiento')->insertGetId(['movimiento_id' => $movimientoId, ...$datos]);
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

    /** Caso 11. */
    public function test_el_lote_y_la_presentacion_deben_ser_del_mismo_producto(): void
    {
        $aceite = $this->productoConLote('Aceite 1 L');
        $fideos = $this->productoConLote('Fideos 500 g');
        $movimiento = $this->movimiento();

        // Lote del aceite con presentación de los fideos.
        $this->assertRechazado(fn () => $this->detalle($movimiento, [
            'producto_id' => $aceite['producto'], 'lote_id' => $aceite['lote'],
            'presentacion_id' => $fideos['caja'], 'cantidad' => 1, 'factor' => 12,
        ]));

        // Lote de los fideos declarado como del aceite.
        $this->assertRechazado(fn () => $this->detalle($movimiento, [
            'producto_id' => $aceite['producto'], 'lote_id' => $fideos['lote'],
            'presentacion_id' => $aceite['caja'], 'cantidad' => 1, 'factor' => 12,
        ]));
    }

    public function test_cantidad_base_es_cantidad_por_factor_con_signo(): void
    {
        $p = $this->productoConLote();
        $movimiento = $this->movimiento(['tipo' => 'conversion', 'motivo' => null]);

        $menosCaja = $this->detalle($movimiento, ['producto_id' => $p['producto'], 'lote_id' => $p['lote'], 'presentacion_id' => $p['caja'], 'cantidad' => -3, 'factor' => 12]);

        $this->assertSame(-36, DetalleMovimiento::find($menosCaja)->cantidad_base);
    }

    public function test_cantidad_cero_y_factor_menor_que_1_se_rechazan(): void
    {
        $p = $this->productoConLote();
        $movimiento = $this->movimiento();
        $base = ['producto_id' => $p['producto'], 'lote_id' => $p['lote'], 'presentacion_id' => $p['base']];

        $this->assertRechazado(fn () => $this->detalle($movimiento, [...$base, 'cantidad' => 0, 'factor' => 1]));
        $this->assertRechazado(fn () => $this->detalle($movimiento, [...$base, 'cantidad' => 5, 'factor' => 0]));
    }

    public function test_el_costo_no_es_negativo_y_solo_va_en_cantidades_positivas(): void
    {
        $p = $this->productoConLote();
        $movimiento = $this->movimiento();
        $base = ['producto_id' => $p['producto'], 'lote_id' => $p['lote'], 'presentacion_id' => $p['base'], 'factor' => 1];

        $this->assertRechazado(fn () => $this->detalle($movimiento, [...$base, 'cantidad' => 5, 'costo_unitario' => -1]));
        $this->assertRechazado(fn () => $this->detalle($movimiento, [...$base, 'cantidad' => -5, 'costo_unitario' => 1]));
    }

    public function test_combinaciones_de_tipo_y_motivo(): void
    {
        $this->assertRechazado(fn () => $this->movimiento(['tipo' => 'entrada', 'motivo' => 'venta_entrega']));
        $this->assertRechazado(fn () => $this->movimiento(['tipo' => 'salida', 'motivo' => null]));
        $this->assertRechazado(fn () => $this->movimiento(['tipo' => 'conversion', 'motivo' => 'otro']));
        $this->assertRechazado(fn () => $this->movimiento(['tipo' => 'ajuste', 'motivo' => 'compra']));

        $this->movimiento(['tipo' => 'salida', 'motivo' => 'vencido']);
        $this->movimiento(['tipo' => 'conversion', 'motivo' => null]);
        $this->assertSame(2, DB::table('movimientos')->count());
    }

    public function test_el_token_y_la_reversion_son_unicos(): void
    {
        $original = $this->movimiento(['token' => '11111111-1111-4111-8111-111111111111']);
        $this->assertRechazado(fn () => $this->movimiento(['token' => '11111111-1111-4111-8111-111111111111']));

        $this->movimiento(['tipo' => 'reversion', 'motivo' => null, 'revierte_movimiento_id' => $original]);
        $this->assertRechazado(fn () => $this->movimiento(['tipo' => 'reversion', 'motivo' => null, 'revierte_movimiento_id' => $original]));
    }

    public function test_identidad_del_lote_incluso_sin_codigo_ni_caducidad(): void
    {
        $p = $this->productoConLote();
        $insertar = fn (string $codigo, ?string $caducidad) => DB::table('lotes')->insert([
            'producto_id' => $p['producto'], 'codigo_lote' => $codigo, 'fecha_caducidad' => $caducidad,
        ]);

        $insertar('', null);
        $this->assertRechazado(fn () => $insertar('', null));

        $insertar('', '2027-03-01');
        $this->assertRechazado(fn () => $insertar('', '2027-03-01'));

        // Mismo código con otra caducidad es otro lote (RF-03).
        $insertar('L1', '2027-06-30');
        $this->assertRechazado(fn () => $insertar('L1', '2027-01-31'));
    }

    public function test_no_se_borra_un_lote_con_movimientos(): void
    {
        $p = $this->productoConLote();
        $this->detalle($this->movimiento(), ['producto_id' => $p['producto'], 'lote_id' => $p['lote'], 'presentacion_id' => $p['base'], 'cantidad' => 5, 'factor' => 1]);

        $this->assertRechazado(fn () => DB::table('lotes')->where('id', $p['lote'])->delete());
    }

    public function test_los_modelos_del_historial_no_se_modifican_ni_se_borran(): void
    {
        $p = $this->productoConLote();
        $movimiento = Movimiento::find($this->movimiento());
        $detalle = DetalleMovimiento::find($this->detalle($movimiento->id, [
            'producto_id' => $p['producto'], 'lote_id' => $p['lote'], 'presentacion_id' => $p['base'], 'cantidad' => 5, 'factor' => 1,
        ]));

        foreach ([
            fn () => $movimiento->update(['observacion' => 'cambio']),
            fn () => $movimiento->delete(),
            fn () => $detalle->update(['cantidad' => 50]),
            fn () => $detalle->delete(),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('El historial aceptó una modificación.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(5, $detalle->fresh()->cantidad);
        $this->assertNull($movimiento->fresh()->observacion);
    }
}
