<?php

namespace Tests\Feature\Inventario;

use App\Enums\MotivoMovimiento;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use App\Services\ExistenciasService;
use App\Services\InventarioService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Preparación común de las pruebas de movimientos: aceite con Botella (base)
 * y Caja x12, con el reloj fijado el 30-10-2026 a las 10:00.
 */
trait PreparaInventario
{
    protected InventarioService $inventario;

    protected User $usuario;

    protected Producto $aceite;

    protected Presentacion $botella;

    protected Presentacion $caja;

    protected function prepararInventario(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 30, 10, 0, 0));
        $this->inventario = new InventarioService;
        $this->usuario = User::factory()->create();
        $this->aceite = $this->crearProducto('Aceite de girasol 1 L');
        $this->botella = $this->aceite->presentacionBase;
        $this->caja = $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
    }

    protected function crearProducto(string $nombre): Producto
    {
        return (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => $nombre,
        ], 'Botella');
    }

    protected function token(): string
    {
        return (string) Str::uuid();
    }

    protected function recibir(Presentacion $presentacion, int $cantidad, string $codigo = 'L1', string $caducidad = '2027-01-31'): Lote
    {
        $resultado = $this->inventario->registrarEntrada(
            $this->usuario, $this->token(), $presentacion->producto, now(), MotivoMovimiento::Compra,
            [['presentacion_id' => $presentacion->id, 'cantidad' => $cantidad, 'codigo_lote' => $codigo, 'fecha_caducidad' => $caducidad]],
        );

        return $resultado->movimiento->detalles->first()->lote;
    }

    protected function existencia(Lote $lote, Presentacion $presentacion): int
    {
        return (new ExistenciasService)->porLoteYPresentacion($lote->producto)
            ->where('lote_id', $lote->id)
            ->where('presentacion_id', $presentacion->id)
            ->sum('cantidad');
    }

    protected function existenciaBase(Lote $lote): int
    {
        return (new ExistenciasService)->lotes($lote->producto, incluirAgotados: true)->firstWhere('id', $lote->id)->existencia_base;
    }

    protected function assertErrorEn(string $campo, callable $operacion, ?string $contiene = null): void
    {
        try {
            $operacion();
            $this->fail("Se esperaba un error de validación en $campo.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors(), 'Errores: '.json_encode($e->errors(), JSON_UNESCAPED_UNICODE));

            if ($contiene !== null) {
                $this->assertStringContainsString($contiene, $e->errors()[$campo][0]);
            }
        }
    }
}
