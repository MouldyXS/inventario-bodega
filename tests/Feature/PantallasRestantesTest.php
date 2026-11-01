<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Inventario\PreparaInventario;
use Tests\TestCase;

/**
 * Resto de formularios, fichas y login (rediseño, etapa 7): se conservan los nombres de
 * campo y los textos; cambian la estructura (tarjeta + barra de acciones), la cabecera y
 * las tablas, que se apilan en pantallas estrechas con la semántica de tabla.
 */
class PantallasRestantesTest extends TestCase
{
    use PreparaInventario, RefreshDatabase;

    private User $admin;

    private Lote $lote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararInventario();
        $this->admin = User::factory()->admin()->create();
        $this->lote = $this->recibir($this->caja, 4, 'L1', '2027-01-31');
    }

    public function test_el_login_conserva_sus_campos_y_ofrece_mostrar_la_contrasena(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('class="login-marca"', $html);
        $this->assertStringContainsString('Inicia sesión para continuar', $html);
        $this->assertMatchesRegularExpression('/<input type="email" id="email" name="email"/', $html);
        $this->assertMatchesRegularExpression('/<input type="password" id="password" name="password" autocomplete="current-password"\s+required/', $html);
        $this->assertStringContainsString('data-mostrar-clave aria-controls="password" aria-pressed="false">Mostrar</button>', $html);
        $this->assertStringNotContainsString('barra-inferior', $html);
        $this->assertStringNotContainsString('barra-lateral', $html);
    }

    public function test_los_errores_del_login_siguen_visibles(): void
    {
        $this->from(route('login'))->followingRedirects()
            ->post(route('login'), ['email' => $this->admin->email, 'password' => 'incorrecta'])
            ->assertSee('El correo o la contraseña no son correctos.');
    }

    public function test_mi_cuenta_y_usuarios_usan_el_campo_de_contrasena_con_boton(): void
    {
        $cuenta = $this->actingAs($this->admin)->get(route('cuenta.edit'))->assertOk()->getContent();
        foreach (['password_actual', 'password', 'password_confirmation'] as $campo) {
            $this->assertStringContainsString('data-mostrar-clave aria-controls="'.$campo.'"', $cuenta);
            $this->assertMatchesRegularExpression('/<input type="password" id="'.$campo.'" name="'.$campo.'"/', $cuenta);
        }
        $this->assertStringContainsString('barra-acciones', $cuenta);

        $usuarios = $this->get(route('usuarios.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input type="password" id="password" name="password"\s+autocomplete="new-password"\s+minlength="8"\s+maxlength="100"\s+required/', $usuarios);
        $this->assertStringContainsString('data-mostrar-clave aria-controls="password"', $usuarios);
    }

    public function test_un_error_de_contrasena_se_muestra_junto_al_campo(): void
    {
        $this->actingAs($this->admin)->from(route('cuenta.edit'))->followingRedirects()
            ->put(route('cuenta.update'), ['password_actual' => 'incorrecta', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123'])
            ->assertSee('is-invalid', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function formularios(): array
    {
        return [
            'categoría nueva' => ['categorias.create'],
            'usuario nuevo' => ['usuarios.create'],
            'mi cuenta' => ['cuenta.edit'],
        ];
    }

    #[DataProvider('formularios')]
    public function test_los_formularios_simples_llevan_cabecera_tarjeta_y_barra_de_acciones(string $ruta): void
    {
        $html = $this->actingAs($this->admin)->get(route($ruta))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<h1 class="h3 mb-0">[^<]+<\/h1>/', $html);
        $this->assertStringContainsString('card card-body mb-3', $html);
        $this->assertStringContainsString('d-flex gap-2 barra-acciones', $html);
        // El formulario ya no es la tarjeta: los botones quedan fuera de ella.
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*class="card card-body"/', $html);
    }

    public function test_los_formularios_de_producto_usan_la_cabecera_con_miniatura(): void
    {
        $html = $this->actingAs($this->admin)->get(route('lotes.edit', $this->lote))->assertOk()->getContent();
        $this->assertStringContainsString('class="cabecera-producto', $html);
        $this->assertStringContainsString('<h1 class="h3 mb-0">Corregir datos del lote</h1>', $html);
        $this->assertStringContainsString('barra-acciones', $html);
        $this->assertStringContainsString('name="codigo_lote"', $html);
        $this->assertStringContainsString('name="fecha_caducidad"', $html);

        $html = $this->get(route('presentaciones.edit', $this->caja))->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="h3 mb-0">Editar presentación</h1>', $html);
        $this->assertStringContainsString('name="factor"', $html);
        $this->assertStringContainsString('barra-acciones', $html);
    }

    public function test_la_ficha_de_existencias_se_apila_y_conserva_sus_acciones(): void
    {
        $html = $this->actingAs($this->admin)->get(route('inventario.show', $this->aceite))->assertOk()->getContent();

        $this->assertStringContainsString('class="cabecera-producto', $html);
        $this->assertStringContainsString('tabla-apilable" role="table"', $html);
        $this->assertStringContainsString('Lotes del producto, con la caducidad más próxima primero', $html);
        foreach (['Caducidad', 'Existencias', 'Total ('.$this->botella->nombre.')', 'Recepciones'] as $etiqueta) {
            $this->assertStringContainsString('data-etiqueta="'.$etiqueta.'"', $html);
        }
        $this->assertStringContainsString(route('conversiones.create', $this->lote), $html);
        $this->assertStringContainsString(route('lotes.edit', $this->lote), $html);
        $this->assertStringContainsString(route('ajustes.create', $this->aceite), $html);
    }

    public function test_la_ficha_de_movimiento_se_apila_y_conserva_la_reversion(): void
    {
        $movimiento = Movimiento::first();

        $html = $this->actingAs($this->admin)->get(route('movimientos.show', $movimiento))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="h3 mb-0">Entrada #'.$movimiento->id.'</h1>', $html);
        $this->assertStringContainsString('tabla-apilable" role="table"', $html);
        foreach (['Lote', 'Caducidad', 'Cantidad', 'Presentación', 'Unidades base', 'Costo unitario'] as $etiqueta) {
            $this->assertStringContainsString('data-etiqueta="'.$etiqueta.'"', $html);
        }
        $this->assertStringContainsString('Revertir este movimiento', $html);
    }

    public function test_la_revision_de_la_carga_inicial_se_apila(): void
    {
        $categoria = Categoria::factory()->create();
        $this->aceite->update(['categoria_id' => $categoria->id]);
        $csv = UploadedFile::fake()->createWithContent('c.csv', "producto;presentacion;cantidad;lote;caducidad;costo\n\"{$this->aceite->nombre}\";Caja x12;3;LX;2027-02-01;\n");

        $html = $this->actingAs($this->admin)->post(route('carga-inicial.revisar'), ['archivo' => $csv])->assertOk()->getContent();

        $this->assertStringContainsString('tabla-apilable" role="table"', $html);
        $this->assertStringContainsString('Filas del archivo a cargar', $html);
        $this->assertStringContainsString('data-etiqueta="Presentación"', $html);
        $this->assertStringContainsString('d-flex gap-2 barra-acciones', $html);
        $this->assertStringContainsString('Todavía no se guardó nada', $html);
    }
}
