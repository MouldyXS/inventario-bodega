<?php

namespace Tests\Feature\Catalogo;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoService;
use GdImage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Imagen principal del producto: miniatura y versión media en WebP, versión en
 * la URL, límites de memoria, orientación EXIF, transparencia y permisos.
 *
 * Las pruebas que procesan imágenes necesitan GD (y algunas EXIF); sin ellas se
 * omiten. Para ejecutarlas sin tocar php.ini:
 *   php -d extension=gd -d extension=exif vendor/phpunit/phpunit/phpunit --filter=ImagenProductoTest
 */
class ImagenProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producto $producto;

    /** @var list<string> */
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->producto = (new CatalogoService)->crearProducto([
            'categoria_id' => Categoria::factory()->create()->id,
            'nombre' => 'Aceite de girasol 1 L',
        ], 'Botella');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        parent::tearDown();
    }

    // --- Ayudas -----------------------------------------------------------------

    private function requiereGd(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('Requiere GD: php -d extension=gd -d extension=exif vendor/phpunit/phpunit/phpunit');
        }
    }

    private function requiereExif(): void
    {
        $this->requiereGd();

        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Requiere EXIF: php -d extension=gd -d extension=exif vendor/phpunit/phpunit/phpunit');
        }
    }

    /**
     * Imagen generada con GD: fondo blanco (JPEG) o transparente (PNG y WebP).
     */
    private function imagen(int $ancho, int $alto, string $tipo = 'jpg', ?callable $pintar = null, ?int $orientacion = null): UploadedFile
    {
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, $tipo === 'jpg' ? imagecolorallocate($lienzo, 255, 255, 255) : imagecolorallocatealpha($lienzo, 0, 0, 0, 127));

        if ($pintar) {
            $pintar($lienzo);
        }

        $ruta = $this->temporal($tipo);
        match ($tipo) {
            'jpg' => imagejpeg($lienzo, $ruta, 92),
            'png' => imagepng($lienzo, $ruta),
            'webp' => imagewebp($lienzo, $ruta, 90),
        };

        if ($orientacion !== null) {
            $this->insertarOrientacion($ruta, $orientacion);
        }

        return new UploadedFile($ruta, "foto.{$tipo}", ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$tipo], null, true);
    }

    /**
     * Añade un segmento APP1 (EXIF) mínimo con la etiqueta Orientation tras el SOI del JPEG.
     */
    private function insertarOrientacion(string $ruta, int $orientacion): void
    {
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1).pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientacion).pack('v', 0)
            .pack('V', 0);
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;
        $jpeg = file_get_contents($ruta);
        file_put_contents($ruta, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
    }

    /**
     * PNG que solo tiene cabecera: getimagesize() lo lee, pero no se puede decodificar.
     */
    private function cabeceraPng(int $ancho, int $alto): UploadedFile
    {
        $ihdr = pack('NN', $ancho, $alto).chr(8).chr(6).chr(0).chr(0).chr(0);
        $ruta = $this->temporal('png');
        file_put_contents($ruta, "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)));

        return new UploadedFile($ruta, 'grande.png', 'image/png', null, true);
    }

    private function temporal(string $extension): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'prueba').'.'.$extension;
        $this->temporales[] = $ruta;

        return $ruta;
    }

    /**
     * @param  array{limite?: int, gd?: bool, webp_vacio?: bool, orientacion?: int}  $opciones
     */
    private function servicio(array $opciones = []): CatalogoService
    {
        return new class($opciones) extends CatalogoService
        {
            public bool $decodificado = false;

            public function __construct(private array $opciones) {}

            protected function limiteMemoria(): int
            {
                return $this->opciones['limite'] ?? parent::limiteMemoria();
            }

            protected function gdDisponible(): bool
            {
                return $this->opciones['gd'] ?? parent::gdDisponible();
            }

            protected function decodificar(string $ruta, int $tipo): GdImage|false
            {
                $this->decodificado = true;

                return parent::decodificar($ruta, $tipo);
            }

            protected function escribirWebp(GdImage $imagen, string $ruta, int $calidad): void
            {
                if ($this->opciones['webp_vacio'] ?? false) {
                    file_put_contents($ruta, '');

                    return;
                }

                parent::escribirWebp($imagen, $ruta, $calidad);
            }

            protected function orientacion(string $ruta, int $tipo): int
            {
                return $this->opciones['orientacion'] ?? parent::orientacion($ruta, $tipo);
            }
        };
    }

    private function generada(string $tamano, ?Producto $producto = null): GdImage
    {
        $producto = ($producto ?? $this->producto)->fresh();
        $ruta = CatalogoService::rutaImagen($producto->id, $producto->imagen, $tamano);
        $this->assertTrue(Storage::disk('local')->exists($ruta), "Falta $ruta");

        return imagecreatefromwebp(Storage::disk('local')->path($ruta));
    }

    /**
     * @return array{r: int, g: int, b: int, a: int}
     */
    private function pixel(GdImage $imagen, int $x, int $y): array
    {
        $color = imagecolorat($imagen, $x, $y);

        return ['r' => ($color >> 16) & 0xFF, 'g' => ($color >> 8) & 0xFF, 'b' => $color & 0xFF, 'a' => ($color >> 24) & 0x7F];
    }

    // --- Subida, sustitución y retirada -------------------------------------------

    public function test_el_admin_sube_una_imagen_y_se_generan_las_dos_versiones_en_webp(): void
    {
        $this->requiereGd();

        $this->actingAs($this->admin)
            ->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(2000, 1500)])
            ->assertRedirect(route('productos.show', $this->producto));

        $producto = $this->producto->fresh();
        $this->assertNotNull($producto->imagen);
        $this->assertEqualsCanonicalizing([
            CatalogoService::rutaImagen($producto->id, $producto->imagen, 'm'),
            CatalogoService::rutaImagen($producto->id, $producto->imagen, 't'),
        ], Storage::disk('local')->allFiles('productos'), 'Solo las dos versiones: el original no se guarda.');

        $media = $this->generada('m');
        $this->assertSame([1000, 750], [imagesx($media), imagesy($media)]);
        $miniatura = $this->generada('t');
        $this->assertSame([160, 120], [imagesx($miniatura), imagesy($miniatura)]);

        $respuesta = $this->get(route('productos.imagen', [$producto, $producto->imagen, 'm']))->assertOk();
        $this->assertSame('image/webp', $respuesta->headers->get('Content-Type'));
        $cache = $respuesta->headers->get('Cache-Control');
        foreach (['private', 'max-age=2592000', 'immutable'] as $directiva) {
            $this->assertStringContainsString($directiva, $cache);
        }

        $this->get(route('productos.show', $producto))->assertSee(route('productos.imagen', [$producto, $producto->imagen, 'm']), false);
    }

    public function test_sustituir_cambia_la_url_y_borra_la_version_anterior_tras_el_commit(): void
    {
        $this->requiereGd();
        $this->actingAs($this->admin)->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(800, 600)]);
        $anterior = $this->producto->fresh()->imagen;

        $this->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(600, 800, 'png')]);
        $nueva = $this->producto->fresh()->imagen;

        $this->assertNotSame($anterior, $nueva);
        Storage::disk('local')->assertMissing(CatalogoService::rutaImagen($this->producto->id, $anterior, 'm'));
        Storage::disk('local')->assertMissing(CatalogoService::rutaImagen($this->producto->id, $anterior, 't'));
        $this->get(route('productos.imagen', [$this->producto, $anterior, 'm']))->assertNotFound();
        $this->get(route('productos.imagen', [$this->producto, $nueva, 'm']))->assertOk();
    }

    public function test_quitar_la_imagen_borra_los_archivos_y_muestra_el_marcador(): void
    {
        $this->requiereGd();
        $this->actingAs($this->admin)->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(800, 600)]);
        $version = $this->producto->fresh()->imagen;

        $this->delete(route('productos.imagen.destroy', $this->producto))->assertRedirect(route('productos.show', $this->producto));

        $this->assertNull($this->producto->fresh()->imagen);
        $this->assertSame([], Storage::disk('local')->allFiles('productos'));
        $this->get(route('productos.imagen', [$this->producto, $version, 't']))->assertNotFound();
        $this->get(route('productos.show', $this->producto))->assertSee('Este producto no tiene imagen.');
    }

    public function test_un_producto_sin_imagen_sigue_funcionando(): void
    {
        $this->actingAs($this->admin)->get(route('productos.show', $this->producto))->assertOk()->assertSee('Este producto no tiene imagen.');
        $this->actingAs(User::factory()->create())->get(route('productos.show', $this->producto))->assertOk()->assertDontSee('imagen-titulo', false);
    }

    public function test_el_listado_muestra_la_miniatura_o_el_marcador(): void
    {
        $this->requiereGd();
        $sin = (new CatalogoService)->crearProducto([
            'categoria_id' => $this->producto->categoria_id,
            'nombre' => 'Zanahoria 1 kg',
        ], 'Funda');
        (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(800, 600));
        $version = $this->producto->fresh()->imagen;

        $this->actingAs(User::factory()->create())->get(route('productos.index'))
            ->assertOk()
            ->assertSee(route('productos.imagen', [$this->producto, $version, 't']), false)
            ->assertDontSee(route('productos.imagen', [$this->producto, $version, 'm']), false)
            ->assertSee('miniatura__marcador', false)
            ->assertSee('>Z<', false);
        $this->assertNull($sin->fresh()->imagen);
    }

    public function test_la_ficha_muestra_la_imagen_media_y_el_administrador_ve_los_controles(): void
    {
        $this->requiereGd();
        (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(800, 600));
        $version = $this->producto->fresh()->imagen;

        $this->actingAs($this->admin)->get(route('productos.show', $this->producto))
            ->assertSee(route('productos.imagen', [$this->producto, $version, 'm']), false)
            ->assertSee('alt="'.$this->producto->nombre.'"', false)
            ->assertSee('Cambiar o quitar imagen')
            ->assertSee('Quitar imagen');

        $this->actingAs(User::factory()->create())->get(route('productos.show', $this->producto))
            ->assertSee(route('productos.imagen', [$this->producto, $version, 'm']), false)
            ->assertDontSee('Cambiar o quitar imagen')
            ->assertDontSee('Quitar imagen');
    }

    // --- Permisos -----------------------------------------------------------------

    public function test_solo_el_administrador_cambia_la_imagen_pero_todos_la_ven(): void
    {
        $this->requiereGd();
        $encargado = User::factory()
            ->conPermiso('puede_gestionar_catalogo')->conPermiso('puede_ajustar_inventario')->conPermiso('puede_revertir')
            ->create();
        $this->actingAs($this->admin)->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(800, 600)]);
        $version = $this->producto->fresh()->imagen;

        $this->actingAs($encargado)->put(route('productos.imagen.update', $this->producto), ['imagen' => $this->imagen(800, 600)])->assertForbidden();
        $this->actingAs($encargado)->delete(route('productos.imagen.destroy', $this->producto))->assertForbidden();
        $this->actingAs($encargado)->get(route('productos.imagen', [$this->producto, $version, 'm']))->assertOk();
        $this->assertSame($version, $this->producto->fresh()->imagen);

        // El servicio también lo comprueba.
        $this->expectException(AuthorizationException::class);
        (new CatalogoService)->quitarImagen($encargado, $this->producto);
    }

    public function test_sin_sesion_no_se_ve_la_imagen(): void
    {
        $this->get(route('productos.imagen', [$this->producto, 'abc', 'm']))->assertRedirect(route('login'));
    }

    public function test_la_imagen_no_se_asigna_desde_el_formulario_del_producto(): void
    {
        $this->actingAs($this->admin)->put(route('productos.update', $this->producto), [
            'categoria_id' => $this->producto->categoria_id,
            'nombre' => $this->producto->nombre,
            'imagen' => 'inventada',
        ])->assertRedirect();

        $this->assertNull($this->producto->fresh()->imagen);
    }

    public function test_cerrar_sesion_pide_borrar_la_cache_del_navegador(): void
    {
        $this->actingAs($this->admin)->post(route('logout'))->assertHeader('Clear-Site-Data', '"cache"');
    }

    // --- Validación y límites (antes de decodificar) --------------------------------

    public function test_tipo_y_peso_no_permitidos(): void
    {
        $this->actingAs($this->admin)
            ->put(route('productos.imagen.update', $this->producto), ['imagen' => UploadedFile::fake()->create('a.gif', 10, 'image/gif')])
            ->assertSessionHasErrors('imagen');
        $this->actingAs($this->admin)
            ->put(route('productos.imagen.update', $this->producto), ['imagen' => UploadedFile::fake()->create('a.jpg', 9 * 1024, 'image/jpeg')])
            ->assertSessionHasErrors('imagen');

        $this->assertNull($this->producto->fresh()->imagen);
    }

    public function test_mas_de_6000_px_por_lado_se_rechaza_sin_decodificar(): void
    {
        $this->requiereGd();
        $servicio = $this->servicio();

        try {
            $servicio->cambiarImagen($this->admin, $this->producto, $this->cabeceraPng(7000, 7000));
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('6000 px por lado', $e->errors()['imagen'][0]);
        }

        $this->assertFalse($servicio->decodificado);
    }

    public function test_con_el_tope_fijo_el_mensaje_indica_16_mp(): void
    {
        $this->requiereGd();
        $servicio = $this->servicio(['limite' => -1]);

        try {
            $servicio->cambiarImagen($this->admin, $this->producto, $this->cabeceraPng(5000, 4000));
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertSame('La imagen tiene 20,0 MP y el máximo es 16 MP. Redúcela a 16 megapíxeles o menos.', $e->errors()['imagen'][0]);
        }

        $this->assertFalse($servicio->decodificado);
    }

    public function test_el_limite_dinamico_aparece_en_el_mensaje_y_no_se_decodifica(): void
    {
        $this->requiereGd();
        // Memoria para ~2 MP de PNG (9 B/px) más el margen de 16 MB.
        $servicio = $this->servicio(['limite' => memory_get_usage(true) + 16 * 1024 * 1024 + 9 * 2_000_000]);

        try {
            $servicio->cambiarImagen($this->admin, $this->producto, $this->imagen(2000, 1500, 'png'));
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $mensaje = $e->errors()['imagen'][0];
            $this->assertMatchesRegularExpression('/^La imagen tiene 3,0 MP y el máximo para PNG en este momento es (1,\d|2,0) MP\. Redúcela o usa JPEG\.$/', $mensaje);
            $this->assertStringNotContainsString('16 MP', $mensaje);
        }

        $this->assertFalse($servicio->decodificado);
        $this->assertNull($this->producto->fresh()->imagen);
    }

    public function test_sin_gd_la_subida_da_un_mensaje_claro(): void
    {
        try {
            $this->servicio(['gd' => false])->cambiarImagen($this->admin, $this->producto, UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg'));
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('GD', $e->errors()['imagen'][0]);
        }
    }

    // --- Dimensiones ------------------------------------------------------------------

    public function test_dimensiones_maximas_por_el_lado_mayor_sin_ampliar(): void
    {
        $casos = [
            [4000, 3000, [1000, 750], [160, 120]],
            [3000, 4000, [750, 1000], [120, 160]],
            [1200, 1200, [1000, 1000], [160, 160]],
            [800, 600, [800, 600], [160, 120]],
        ];

        foreach ($casos as [$ancho, $alto, $media, $miniatura]) {
            $obtenida = CatalogoService::dimensionesMaximas($ancho, $alto, CatalogoService::IMAGEN_LADO_MEDIA);
            $this->assertSame($media, $obtenida);
            $this->assertSame($miniatura, CatalogoService::dimensionesMaximas($obtenida[0], $obtenida[1], CatalogoService::IMAGEN_LADO_MINIATURA));
        }

        // Nunca un lado de 0 px.
        $this->assertSame([1000, 1], CatalogoService::dimensionesMaximas(5000, 2, 1000));
    }

    public function test_dimensiones_finales_de_las_imagenes_generadas(): void
    {
        $this->requiereGd();

        foreach ([[1200, 1600, [750, 1000], [120, 160]], [800, 600, [800, 600], [160, 120]]] as [$ancho, $alto, $media, $miniatura]) {
            (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen($ancho, $alto));

            $m = $this->generada('m');
            $t = $this->generada('t');
            $this->assertSame($media, [imagesx($m), imagesy($m)]);
            $this->assertSame($miniatura, [imagesx($t), imagesy($t)]);
        }
    }

    // --- Orientación EXIF -------------------------------------------------------------

    public function test_las_ocho_orientaciones_exif_quedan_aplicadas(): void
    {
        $this->requiereExif();
        $rojo = function (GdImage $lienzo) {
            imagefilledrectangle($lienzo, 0, 0, 199, 199, imagecolorallocate($lienzo, 255, 0, 0));
        };

        // Original 1600×1200 con un cuadrado rojo arriba a la izquierda: tras orientar,
        // las dimensiones y la esquina del cuadrado distinguen giros de espejos.
        $esperado = [
            1 => [1000, 750, 'arriba-izquierda'],
            2 => [1000, 750, 'arriba-derecha'],
            3 => [1000, 750, 'abajo-derecha'],
            4 => [1000, 750, 'abajo-izquierda'],
            5 => [750, 1000, 'arriba-izquierda'],
            6 => [750, 1000, 'arriba-derecha'],
            7 => [750, 1000, 'abajo-derecha'],
            8 => [750, 1000, 'abajo-izquierda'],
        ];

        foreach ($esperado as $orientacion => [$ancho, $alto, $esquina]) {
            (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(1600, 1200, 'jpg', $rojo, $orientacion));
            $media = $this->generada('m');

            $this->assertSame([$ancho, $alto], [imagesx($media), imagesy($media)], "Orientación $orientacion");

            $esquinas = [
                'arriba-izquierda' => [20, 20],
                'arriba-derecha' => [$ancho - 21, 20],
                'abajo-derecha' => [$ancho - 21, $alto - 21],
                'abajo-izquierda' => [20, $alto - 21],
            ];

            foreach ($esquinas as $nombre => [$x, $y]) {
                $p = $this->pixel($media, $x, $y);
                $esRojo = $p['r'] > 180 && $p['g'] < 90 && $p['b'] < 90;
                $this->assertSame($nombre === $esquina, $esRojo, "Orientación $orientacion, esquina $nombre");
            }
        }
    }

    // --- Transparencia --------------------------------------------------------------------

    /**
     * Fondo transparente y cuadrado opaco en el centro: las esquinas deben seguir
     * transparentes (alfa 127) y el centro opaco, en la media y en la miniatura.
     */
    private function comprobarTransparencia(int $ancho, int $alto): void
    {
        foreach (['m', 't'] as $tamano) {
            $imagen = $this->generada($tamano);
            [$w, $h] = [imagesx($imagen), imagesy($imagen)];

            if ($tamano === 'm') {
                $this->assertSame([$ancho, $alto], [$w, $h]);
            }

            foreach ([[1, 1], [$w - 2, 1], [1, $h - 2], [$w - 2, $h - 2]] as [$x, $y]) {
                $this->assertSame(127, $this->pixel($imagen, $x, $y)['a'], "Esquina ($x, $y) de $tamano");
            }

            $this->assertSame(0, $this->pixel($imagen, intdiv($w, 2), intdiv($h, 2))['a'], "Centro de $tamano");
        }
    }

    private function pintarCentroOpaco(int $ancho, int $alto): callable
    {
        return function (GdImage $lienzo) use ($ancho, $alto) {
            imagefilledrectangle($lienzo, intdiv($ancho, 4), intdiv($alto, 4), intdiv($ancho * 3, 4), intdiv($alto * 3, 4), imagecolorallocatealpha($lienzo, 20, 120, 220, 0));
        };
    }

    public function test_un_png_transparente_conserva_el_alfa_en_webp(): void
    {
        $this->requiereGd();

        (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(400, 300, 'png', $this->pintarCentroOpaco(400, 300)));

        $this->comprobarTransparencia(400, 300);
    }

    public function test_el_alfa_se_conserva_al_girar(): void
    {
        $this->requiereGd();

        // Se fuerza la orientación 6 (giro de 90°) sobre un PNG para recorrer imagerotate.
        $this->servicio(['orientacion' => 6])->cambiarImagen($this->admin, $this->producto, $this->imagen(400, 300, 'png', $this->pintarCentroOpaco(400, 300)));

        $this->comprobarTransparencia(300, 400);
    }

    public function test_un_webp_con_alfa_conserva_la_transparencia(): void
    {
        $this->requiereGd();

        (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(400, 300, 'webp', $this->pintarCentroOpaco(400, 300)));

        $this->comprobarTransparencia(400, 300);
    }

    // --- Integridad y fallos -----------------------------------------------------------

    public function test_un_webp_vacio_no_se_guarda_y_la_fila_no_cambia(): void
    {
        $this->requiereGd();

        try {
            $this->servicio(['webp_vacio' => true])->cambiarImagen($this->admin, $this->producto, $this->imagen(800, 600));
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $e) {
            $this->assertSame('No se pudo procesar la imagen; inténtalo con otra foto.', $e->errors()['imagen'][0]);
        }

        $this->assertSame([], Storage::disk('local')->allFiles('productos'));
        $this->assertNull($this->producto->fresh()->imagen);
    }

    public function test_si_falla_la_transaccion_no_quedan_archivos_nuevos(): void
    {
        $this->requiereGd();
        (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(800, 600));
        $vigente = $this->producto->fresh()->imagen;

        Producto::updating(fn () => throw new RuntimeException('Fallo simulado'));

        try {
            (new CatalogoService)->cambiarImagen($this->admin, $this->producto, $this->imagen(600, 800));
            $this->fail('Se esperaba el fallo simulado.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        }

        $this->assertSame($vigente, $this->producto->fresh()->imagen);
        $this->assertEqualsCanonicalizing([
            CatalogoService::rutaImagen($this->producto->id, $vigente, 'm'),
            CatalogoService::rutaImagen($this->producto->id, $vigente, 't'),
        ], Storage::disk('local')->allFiles('productos'));
    }

    public function test_se_borra_la_version_que_tiene_la_fila_no_la_del_modelo_recibido(): void
    {
        $this->requiereGd();
        $servicio = new CatalogoService;
        $servicio->cambiarImagen($this->admin, $this->producto, $this->imagen(800, 600));
        $desactualizado = Producto::find($this->producto->id);
        $antigua = $desactualizado->imagen;

        // Otro administrador la cambia mientras tanto.
        $servicio->cambiarImagen($this->admin, Producto::find($this->producto->id), $this->imagen(600, 800));
        $intermedia = $this->producto->fresh()->imagen;

        // Se sustituye usando el modelo que aún cree que la vigente es la antigua.
        $servicio->cambiarImagen($this->admin, $desactualizado, $this->imagen(500, 500));
        $final = $this->producto->fresh()->imagen;

        $this->assertNotSame($intermedia, $final);
        $this->assertEqualsCanonicalizing([
            CatalogoService::rutaImagen($this->producto->id, $final, 'm'),
            CatalogoService::rutaImagen($this->producto->id, $final, 't'),
        ], Storage::disk('local')->allFiles('productos'), 'Debe quedar solo la versión final: la intermedia no queda huérfana.');
        $this->assertNotSame($antigua, $final);
    }

    // --- Comandos y Configuración ---------------------------------------------------------

    public function test_imagenes_huerfanas_lista_y_solo_borra_con_la_opcion(): void
    {
        $disco = Storage::disk('local');
        $this->producto->forceFill(['imagen' => 'vigente'])->save();
        $vigentes = [CatalogoService::rutaImagen($this->producto->id, 'vigente', 'm'), CatalogoService::rutaImagen($this->producto->id, 'vigente', 't')];
        $huerfanas = [CatalogoService::rutaImagen($this->producto->id, 'vieja', 'm'), 'productos/999/x-t.webp'];

        foreach ([...$vigentes, ...$huerfanas] as $ruta) {
            $disco->put($ruta, 'x');
        }

        $this->artisan('inventario:imagenes-huerfanas')
            ->expectsOutput($huerfanas[0])->expectsOutput($huerfanas[1])->assertSuccessful();
        $disco->assertExists($huerfanas);

        $this->artisan('inventario:imagenes-huerfanas', ['--borrar' => true])->assertSuccessful();
        $disco->assertMissing($huerfanas);
        $disco->assertExists($vigentes);
        $this->assertFalse($disco->exists('productos/999'));
    }

    public function test_configuracion_y_verificar_permisos_muestran_el_estado_de_las_imagenes(): void
    {
        $this->actingAs($this->admin)->get(route('configuracion.edit'))
            ->assertOk()
            ->assertSee('Imágenes de productos')
            ->assertSee('Orientación de fotos (EXIF)')
            ->assertSee('Subida máxima / formulario');

        $this->artisan('inventario:verificar-permisos')->expectsOutputToContain('Imágenes: GD');
    }
}
