<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RespaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso del estado de las copias de seguridad (Requerimientos RNF-05, decisión K3).
 * El JSON lo escribe scripts/respaldo.ps1; hoy es el 30-10-2026 a las 12:00.
 */
class RespaldoEstadoTest extends TestCase
{
    use RefreshDatabase;

    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDateTime(2026, 10, 30, 12, 0, 0));
        $this->archivo = tempnam(sys_get_temp_dir(), 'respaldo');
        unlink($this->archivo);
        config(['inventario.respaldo.archivo_estado' => $this->archivo]);
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function escribir(array $cambios = [], string $prefijo = ''): void
    {
        file_put_contents($this->archivo, $prefijo.json_encode([
            'ultimo_intento' => '2026-10-30T11:30:00.0000000-05:00',
            'ok' => true,
            'ultimo_ok' => '2026-10-30T11:30:00.0000000-05:00',
            'archivo' => 'inventario_20261030_1130.7z',
            'bytes' => 3025,
            'error' => '',
            ...$cambios,
        ]));
    }

    public function test_sin_archivo_de_estado_no_hay_copias_registradas(): void
    {
        $estado = (new RespaldoService)->estado();

        $this->assertFalse($estado['instalado']);
        $this->assertTrue($estado['atrasado']);
    }

    public function test_una_copia_reciente_no_esta_atrasada_y_se_lee_aunque_tenga_bom(): void
    {
        $this->escribir(prefijo: "\xEF\xBB\xBF");

        $estado = (new RespaldoService)->estado();

        $this->assertTrue($estado['instalado']);
        $this->assertFalse($estado['atrasado']);
        $this->assertSame(0, $estado['antiguedad_horas']);
        $this->assertSame('30/10/2026 11:30', $estado['ultimo_ok']->format('d/m/Y H:i'));
    }

    public function test_pasadas_36_horas_se_considera_atrasada(): void
    {
        $this->escribir(['ultimo_ok' => '2026-10-28T23:30:00.0000000-05:00']);
        $this->assertTrue((new RespaldoService)->estado()['atrasado']);

        // 36 horas exactas todavía no.
        $this->escribir(['ultimo_ok' => '2026-10-29T00:00:00.0000000-05:00']);
        $this->assertFalse((new RespaldoService)->estado()['atrasado']);
    }

    public function test_un_intento_fallido_conserva_la_ultima_copia_correcta(): void
    {
        $this->escribir(['ok' => false, 'error' => 'mysqldump falló', 'ultimo_intento' => '2026-10-30T11:55:00.0000000-05:00']);

        $estado = (new RespaldoService)->estado();

        $this->assertFalse($estado['ultimo_intento_ok']);
        $this->assertSame('mysqldump falló', $estado['error']);
        $this->assertFalse($estado['atrasado']);
    }

    public function test_un_archivo_corrupto_se_trata_como_sin_copias(): void
    {
        file_put_contents($this->archivo, '{no es json');

        $this->assertFalse((new RespaldoService)->estado()['instalado']);
    }

    public function test_el_panel_avisa_solo_al_administrador_y_solo_si_hace_falta(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('inicio'))
            ->assertSee('No hay copias de seguridad registradas');
        $this->actingAs(User::factory()->create())->get(route('inicio'))
            ->assertDontSee('Copias de seguridad');

        $this->escribir();
        $this->actingAs(User::factory()->admin()->create())->get(route('inicio'))
            ->assertDontSee('Copias de seguridad:');

        $this->escribir(['ultimo_ok' => '2026-10-20T00:00:00.0000000-05:00']);
        $this->actingAs(User::factory()->admin()->create())->get(route('inicio'))
            ->assertSee('Copias de seguridad:')
            ->assertSee('hace más de 36 horas');
    }

    public function test_configuracion_muestra_el_estado_de_las_copias(): void
    {
        $this->escribir();

        $this->actingAs(User::factory()->admin()->create())->get(route('configuracion.edit'))
            ->assertSee('Última copia correcta:')
            ->assertSee('30/10/2026 11:30');
    }
}
