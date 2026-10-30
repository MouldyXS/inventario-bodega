<?php

namespace Tests\Feature\Inventario;

use App\Enums\TipoMovimiento;
use App\Models\Movimiento;
use App\Models\User;
use Tests\TestCase;

/**
 * Salidas y reversiones simultáneas desde procesos independientes
 * (Requerimientos §5.6, Caso 14).
 */
class ConcurrenciaSalidasTest extends TestCase
{
    use LanzaProcesos, PreparaInventario;

    private const PROCESOS = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        // Los procesos trabajadores usan su propio reloj; las pruebas no dependen de la hora.
        $this->travelBack();
    }

    /** Caso 14. */
    public function test_salidas_simultaneas_nunca_dejan_existencias_negativas(): void
    {
        $lote = $this->recibir($this->botella, 10);

        $respuestas = $this->lanzar(array_map(fn () => [
            'operacion' => 'salida',
            'usuario_id' => $this->usuario->id,
            'token' => $this->token(),
            'producto_id' => $this->aceite->id,
            'lote_id' => $lote->id,
            'presentacion_id' => $this->botella->id,
            'cantidad' => 3,
        ], range(1, self::PROCESOS)));

        $this->assertSinFallos($respuestas);

        // Caben 3 salidas de 3 en 10 unidades; las otras 2 se rechazan por existencias.
        $aceptadas = collect($respuestas)->where('ok', true);
        $rechazadas = collect($respuestas)->where('rechazado', true);
        $this->assertCount(3, $aceptadas);
        $this->assertCount(2, $rechazadas);
        foreach ($rechazadas as $rechazo) {
            $this->assertStringStartsWith('Solo hay 1 Botella', $rechazo['errores']['lineas.0.cantidad'][0]);
        }

        $this->assertSame(3, Movimiento::where('tipo', TipoMovimiento::Salida)->count());
        $this->assertSame(1, $this->existencia($lote, $this->botella));
    }

    public function test_reversiones_simultaneas_del_mismo_movimiento_registran_una_sola(): void
    {
        $lote = $this->recibir($this->caja, 10);
        $entrada = Movimiento::sole();
        $revisor = User::factory()->conPermiso('puede_revertir')->create();

        $respuestas = $this->lanzar(array_map(fn () => [
            'operacion' => 'revertir',
            'usuario_id' => $revisor->id,
            'token' => $this->token(),
            'movimiento_id' => $entrada->id,
        ], range(1, self::PROCESOS)));

        $this->assertSinFallos($respuestas);
        $this->assertCount(1, collect($respuestas)->where('ok', true));
        foreach (collect($respuestas)->where('rechazado', true) as $rechazo) {
            $this->assertSame(['Este movimiento ya fue revertido.'], $rechazo['errores']['movimiento']);
        }

        $this->assertSame(1, Movimiento::where('tipo', TipoMovimiento::Reversion)->count());
        $this->assertSame(0, $this->existencia($lote, $this->caja));
    }
}
