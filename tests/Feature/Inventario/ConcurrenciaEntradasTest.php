<?php

namespace Tests\Feature\Inventario;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use App\Services\ExistenciasService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Entradas simultáneas desde procesos independientes (Requerimientos §5.6, Caso 15).
 */
class ConcurrenciaEntradasTest extends TestCase
{
    use LanzaProcesos;

    private const PROCESOS = 5;

    private User $usuario;

    private Producto $aceite;

    private Presentacion $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->aceite = (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Aceite de girasol 1 L',
        ], 'Botella');
        $this->caja = $this->aceite->presentaciones()->create(['nombre' => 'Caja x12', 'factor' => 12]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entrada(string $token): array
    {
        return [
            'operacion' => 'entrada',
            'usuario_id' => $this->usuario->id,
            'token' => $token,
            'producto_id' => $this->aceite->id,
            'presentacion_id' => $this->caja->id,
            'codigo_lote' => 'L-CONCURRENTE',
            'fecha_caducidad' => '2027-01-31',
            'cantidad' => 2,
        ];
    }

    /** Caso 15. */
    public function test_el_mismo_token_en_paralelo_registra_un_solo_movimiento(): void
    {
        $token = (string) Str::uuid();

        $respuestas = $this->lanzar(array_fill(0, self::PROCESOS, $this->entrada($token)));

        foreach ($respuestas as $respuesta) {
            $this->assertTrue($respuesta['ok'], $respuesta['error'] ?? json_encode($respuesta['errores'] ?? []));
        }

        $this->assertSame(1, Movimiento::count());
        $this->assertCount(1, array_unique(array_column($respuestas, 'movimiento')));
        // Uno lo registró; los demás recibieron "ya estaba registrado".
        $this->assertSame(1, collect($respuestas)->where('reenvio', false)->count());
        $this->assertSame(24, (new ExistenciasService)->lotes($this->aceite)->sole()->existencia_base);
    }

    public function test_recepciones_simultaneas_del_mismo_lote_nuevo_crean_un_solo_lote(): void
    {
        $respuestas = $this->lanzar(array_map(fn () => $this->entrada((string) Str::uuid()), range(1, self::PROCESOS)));

        foreach ($respuestas as $respuesta) {
            $this->assertTrue($respuesta['ok'], $respuesta['error'] ?? json_encode($respuesta['errores'] ?? []));
            $this->assertFalse($respuesta['reenvio']);
        }

        $this->assertSame(self::PROCESOS, Movimiento::count());
        $this->assertSame(1, Lote::count());
        $this->assertSame(self::PROCESOS * 24, (new ExistenciasService)->lotes($this->aceite)->sole()->existencia_base);
    }
}
