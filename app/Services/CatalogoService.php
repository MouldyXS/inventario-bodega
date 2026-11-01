<?php

namespace App\Services;

use App\Models\DetalleMovimiento;
use App\Models\Lote;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reglas del catálogo que no puede garantizar MySQL (Requerimientos §5.7).
 */
class CatalogoService
{
    /** Imagen de producto: lado mayor de la versión media y de la miniatura. */
    public const IMAGEN_LADO_MEDIA = 1000;

    public const IMAGEN_LADO_MINIATURA = 160;

    /** Peso máximo del archivo subido (KB) y límites de la imagen original. */
    public const IMAGEN_MAX_KB = 8 * 1024;

    public const IMAGEN_MAX_LADO = 6000;

    public const IMAGEN_MAX_PIXELES = 16_000_000;

    /** Memoria reservada para el resto de la petición al calcular el límite de píxeles. */
    private const IMAGEN_MARGEN_MEMORIA = 16 * 1024 * 1024;

    /**
     * Bytes por píxel al decodificar, medidos con la GD instalada (pico 4,5 B/px
     * en JPEG y WebP, 8,1 B/px en PNG) más un margen del ~10 %.
     */
    private const IMAGEN_BYTES_POR_PIXEL = [IMAGETYPE_JPEG => 5, IMAGETYPE_WEBP => 5, IMAGETYPE_PNG => 9];

    private const IMAGEN_FORMATOS = [IMAGETYPE_JPEG => 'JPEG', IMAGETYPE_PNG => 'PNG', IMAGETYPE_WEBP => 'WebP'];

    /**
     * RF-02: el producto y su presentación base (factor 1) se crean en la
     * misma transacción, de modo que nunca existe un producto sin base.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crearProducto(array $datos, string $nombreBase): Producto
    {
        return DB::transaction(function () use ($datos, $nombreBase) {
            $producto = Producto::create($datos);

            $base = new Presentacion(['nombre' => $nombreBase, 'factor' => 1]);
            $base->es_base = true;
            $producto->presentaciones()->save($base);

            return $producto;
        });
    }

    /**
     * RF-01: productos con el mismo nombre y la misma marca, sin distinguir
     * mayúsculas ni espacios repetidos. La intercalación de la base ya ignora
     * mayúsculas; aquí se normalizan los espacios. Sin marca equivale a marca vacía.
     *
     * @return Collection<int, Producto>
     */
    public function posiblesDuplicados(string $nombre, ?string $marca): Collection
    {
        return Producto::query()
            ->whereRaw("REGEXP_REPLACE(TRIM(nombre), '[[:space:]]+', ' ') = ?", [Str::squish($nombre)])
            ->whereRaw("REGEXP_REPLACE(TRIM(COALESCE(marca, '')), '[[:space:]]+', ' ') = ?", [Str::squish($marca ?? '')])
            ->orderBy('id')
            ->get();
    }

    /**
     * RF-01: el control de caducidad no cambia si el producto ya tiene lotes o
     * movimientos (Caso 20). La fila se bloquea con FOR UPDATE; las entradas la
     * leen con FOR SHARE, así que no pueden cruzarse con este cambio.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarProducto(Producto $producto, array $datos): void
    {
        DB::transaction(function () use ($producto, $datos) {
            $actual = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('controla_caducidad', $datos)
                && (bool) $datos['controla_caducidad'] !== $actual->controla_caducidad
                && (Lote::where('producto_id', $actual->id)->exists() || DetalleMovimiento::where('producto_id', $actual->id)->exists())) {
                throw ValidationException::withMessages([
                    'controla_caducidad' => 'El control de caducidad no puede cambiarse: el producto ya tiene lotes.',
                ]);
            }

            $producto->update($datos);
        });
    }

    /**
     * RF-02: la presentación base conserva el factor 1 y no puede desactivarse;
     * de ella solo cambia el nombre. El factor de cualquier presentación con
     * movimientos es fijo (Caso 19) y una presentación con existencias no se
     * desactiva. La fila se bloquea con FOR UPDATE; las entradas la leen con FOR SHARE.
     *
     * @param  array{nombre?: string, factor?: int, activo?: bool}  $datos
     */
    public function actualizarPresentacion(Presentacion $presentacion, array $datos): void
    {
        DB::transaction(function () use ($presentacion, $datos) {
            $actual = Presentacion::whereKey($presentacion->id)->lockForUpdate()->firstOrFail();
            $cambiaFactor = array_key_exists('factor', $datos) && (int) $datos['factor'] !== $actual->factor;
            $desactiva = array_key_exists('activo', $datos) && ! $datos['activo'] && $actual->activo;

            if ($actual->es_base && $cambiaFactor) {
                throw ValidationException::withMessages(['factor' => 'La presentación base siempre tiene factor 1.']);
            }

            if ($actual->es_base && $desactiva) {
                throw ValidationException::withMessages(['activo' => 'La presentación base no puede desactivarse.']);
            }

            if ($cambiaFactor && DetalleMovimiento::where('presentacion_id', $actual->id)->exists()) {
                throw ValidationException::withMessages([
                    'factor' => 'El factor no puede cambiarse porque la presentación ya tiene movimientos. Crea una presentación nueva.',
                ]);
            }

            if ($desactiva && (int) DetalleMovimiento::where('presentacion_id', $actual->id)->sum('cantidad') !== 0) {
                throw ValidationException::withMessages([
                    'activo' => 'La presentación tiene existencias: no puede desactivarse hasta que se agoten.',
                ]);
            }

            $presentacion->update($datos);
        });
    }

    // --- Imagen principal del producto ------------------------------------------

    /**
     * Ruta en el disco local (privado) de una versión de la imagen; $tamano: 'm' (media) o 't' (miniatura).
     */
    public static function rutaImagen(int $productoId, string $version, string $tamano): string
    {
        return "productos/{$productoId}/{$version}-{$tamano}.webp";
    }

    /**
     * Sustituye (o añade) la imagen. Solo el administrador. Se generan primero
     * la versión media y la miniatura; después se guarda la versión nueva con la
     * fila bloqueada y, solo tras el commit, se borran los archivos de la versión
     * que tenía la fila en ese momento (no la del modelo recibido, que puede
     * estar desactualizado). Si la transacción falla, se borran los nuevos.
     */
    public function cambiarImagen(User $actor, Producto $producto, UploadedFile $archivo): void
    {
        Gate::forUser($actor)->authorize('administrar');

        $version = Str::lower((string) Str::ulid());
        $nuevos = $this->generarImagenes($producto->id, $version, $archivo->getRealPath());

        try {
            $this->guardarVersion($producto, $version);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($nuevos);

            throw $e;
        }
    }

    /**
     * Quita la imagen. Solo el administrador. Los archivos se borran tras el commit.
     */
    public function quitarImagen(User $actor, Producto $producto): void
    {
        Gate::forUser($actor)->authorize('administrar');

        $this->guardarVersion($producto, null);
    }

    /**
     * Estado del entorno para las imágenes (Configuración e inventario:verificar-permisos).
     * Se lee en el proceso que atiende la petición, así refleja lo que ve Apache.
     *
     * @return array{gd: bool, webp: bool, exif: bool, subida_mb: float, post_mb: float, memoria: string, subida_suficiente: bool}
     */
    public function estadoImagenes(): array
    {
        $subida = self::bytesIni((string) ini_get('upload_max_filesize'));
        $post = self::bytesIni((string) ini_get('post_max_size'));

        return [
            'gd' => extension_loaded('gd'),
            'webp' => $this->gdDisponible(),
            'exif' => function_exists('exif_read_data'),
            'subida_mb' => round($subida / 1048576, 1),
            'post_mb' => round($post / 1048576, 1),
            'memoria' => (string) ini_get('memory_limit'),
            // 8 MB de imagen más el resto del formulario (docs/OPERACION.md).
            'subida_suficiente' => $subida >= 10 * 1048576 && ($post <= 0 || $post >= 12 * 1048576),
        ];
    }

    /**
     * Dimensiones que caben en $ladoMax por el lado mayor, sin ampliar nunca.
     *
     * @return array{0: int, 1: int}
     */
    public static function dimensionesMaximas(int $ancho, int $alto, int $ladoMax): array
    {
        $escala = min(1, $ladoMax / max($ancho, $alto));

        return [max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala))];
    }

    private function guardarVersion(Producto $producto, ?string $version): void
    {
        DB::transaction(function () use ($producto, $version) {
            $actual = Producto::whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $anterior = $actual->imagen;

            $actual->imagen = $version;
            $actual->save();
            $producto->imagen = $version;

            if ($anterior !== null && $anterior !== $version) {
                DB::afterCommit(fn () => Storage::disk('local')->delete([
                    self::rutaImagen($producto->id, $anterior, 'm'),
                    self::rutaImagen($producto->id, $anterior, 't'),
                ]));
            }
        });
    }

    /**
     * Valida el archivo antes de decodificarlo y genera las dos versiones en WebP.
     *
     * @return list<string> Rutas guardadas en el disco local.
     */
    private function generarImagenes(int $productoId, string $version, string $ruta): array
    {
        if (! $this->gdDisponible()) {
            throw ValidationException::withMessages(['imagen' => 'El servidor no puede procesar imágenes: falta la extensión GD con WebP (ver docs/OPERACION.md).']);
        }

        // getimagesize() solo lee la cabecera: tamaño y tipo sin decodificar.
        $info = @getimagesize($ruta);

        if ($info === false || ! isset(self::IMAGEN_FORMATOS[$info[2]])) {
            throw ValidationException::withMessages(['imagen' => 'El archivo no es una imagen JPEG, PNG o WebP válida.']);
        }

        [$ancho, $alto, $tipo] = $info;
        $this->comprobarTamano($ancho, $alto, $tipo);

        $orientacion = $this->orientacion($ruta, $tipo);
        $original = $this->decodificar($ruta, $tipo);

        if ($original === false) {
            throw ValidationException::withMessages(['imagen' => 'No se pudo leer la imagen; inténtalo con otra foto.']);
        }

        // Se escala antes de girar: el giro no cambia el lado mayor y así solo se gira la versión media.
        [$anchoMedia, $altoMedia] = self::dimensionesMaximas($ancho, $alto, self::IMAGEN_LADO_MEDIA);
        $media = $this->escalar($original, $anchoMedia, $altoMedia);
        unset($original); // libera el original antes de seguir (imagedestroy() está obsoleta desde PHP 8.5)

        $media = $this->orientar($media, $orientacion);
        [$anchoMini, $altoMini] = self::dimensionesMaximas(imagesx($media), imagesy($media), self::IMAGEN_LADO_MINIATURA);
        $miniatura = $this->escalar($media, $anchoMini, $altoMini);

        $temporales = [];
        $guardados = [];

        try {
            foreach ([[$media, 80], [$miniatura, 75]] as [$imagen, $calidad]) {
                $temporal = tempnam(sys_get_temp_dir(), 'img');
                $temporales[] = $temporal;
                $this->escribirWebp($imagen, $temporal, $calidad);
                $this->verificarWebp($temporal, imagesx($imagen), imagesy($imagen));
            }

            foreach (['m', 't'] as $i => $tamano) {
                $destino = self::rutaImagen($productoId, $version, $tamano);
                $guardado = Storage::disk('local')->putFileAs(dirname($destino), new File($temporales[$i]), basename($destino));

                if ($guardado === false) {
                    throw ValidationException::withMessages(['imagen' => 'No se pudo guardar la imagen; inténtalo de nuevo.']);
                }

                $guardados[] = $destino;
            }

            return $guardados;
        } catch (Throwable $e) {
            Storage::disk('local')->delete($guardados);

            throw $e;
        } finally {
            foreach ($temporales as $temporal) {
                @unlink($temporal);
            }
        }
    }

    /**
     * Límite por lado y límite de píxeles calculado con la memoria disponible
     * (memory_get_usage(true): bloques reservados, lo que se compara con memory_limit).
     */
    private function comprobarTamano(int $ancho, int $alto, int $tipo): void
    {
        if (max($ancho, $alto) > self::IMAGEN_MAX_LADO) {
            throw ValidationException::withMessages([
                'imagen' => "La imagen mide {$ancho} × {$alto} px; el máximo es ".self::IMAGEN_MAX_LADO.' px por lado. Redúcela.',
            ]);
        }

        $limite = $this->limiteMemoria();
        $maximo = $limite < 0
            ? self::IMAGEN_MAX_PIXELES
            : max(0, min(self::IMAGEN_MAX_PIXELES, intdiv($limite - memory_get_usage(true) - self::IMAGEN_MARGEN_MEMORIA, self::IMAGEN_BYTES_POR_PIXEL[$tipo])));

        if ($ancho * $alto <= $maximo) {
            return;
        }

        $tiene = number_format($ancho * $alto / 1e6, 1, ',', '.');

        if ($maximo === self::IMAGEN_MAX_PIXELES) {
            $mensaje = "La imagen tiene {$tiene} MP y el máximo es ".(self::IMAGEN_MAX_PIXELES / 1e6).' MP. Redúcela a '.(self::IMAGEN_MAX_PIXELES / 1e6).' megapíxeles o menos.';
        } else {
            $formato = self::IMAGEN_FORMATOS[$tipo];
            $permitido = number_format(floor($maximo / 1e5) / 10, 1, ',', '.');
            $mensaje = "La imagen tiene {$tiene} MP y el máximo para {$formato} en este momento es {$permitido} MP. Redúcela"
                .($tipo === IMAGETYPE_PNG ? ' o usa JPEG.' : '.');
        }

        throw ValidationException::withMessages(['imagen' => $mensaje]);
    }

    /**
     * Orientación EXIF (1 a 8) de una foto JPEG; 1 si no hay etiqueta o falta la extensión exif.
     */
    protected function orientacion(string $ruta, int $tipo): int
    {
        if ($tipo !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 1;
        }

        $orientacion = (int) (@exif_read_data($ruta)['Orientation'] ?? 1);

        return $orientacion >= 1 && $orientacion <= 8 ? $orientacion : 1;
    }

    protected function decodificar(string $ruta, int $tipo): GdImage|false
    {
        $imagen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
        };

        if ($imagen !== false && ! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        return $imagen;
    }

    protected function escribirWebp(GdImage $imagen, string $ruta, int $calidad): void
    {
        imagewebp($imagen, $ruta, $calidad);
    }

    protected function gdDisponible(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false) === true;
    }

    /**
     * memory_limit en bytes; -1 si no hay límite.
     */
    protected function limiteMemoria(): int
    {
        $valor = (string) ini_get('memory_limit');

        return $valor === '-1' ? -1 : self::bytesIni($valor);
    }

    /**
     * Lienzo transparente del tamaño pedido (ancho y alto explícitos), sobre el que se copia.
     */
    private function escalar(GdImage $origen, int $ancho, int $alto): GdImage
    {
        $destino = imagecreatetruecolor($ancho, $alto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $ancho, $alto, imagesx($origen), imagesy($origen));

        return $destino;
    }

    /**
     * Aplica la orientación EXIF (imagerotate gira en sentido antihorario).
     */
    private function orientar(GdImage $imagen, int $orientacion): GdImage
    {
        $giro = match ($orientacion) {
            3 => 180,
            5, 6, 7 => 270,
            8 => 90,
            default => 0,
        };

        if ($giro !== 0) {
            $imagen = imagerotate($imagen, $giro, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
        }

        match ($orientacion) {
            2, 5 => imageflip($imagen, IMG_FLIP_HORIZONTAL),
            4, 7 => imageflip($imagen, IMG_FLIP_VERTICAL),
            default => null,
        };

        return $imagen;
    }

    /**
     * No se confía en el valor de retorno de imagewebp(): el archivo debe existir,
     * no estar vacío y leerse como WebP con las dimensiones esperadas.
     */
    private function verificarWebp(string $ruta, int $ancho, int $alto): void
    {
        clearstatcache(true, $ruta);
        $info = is_file($ruta) && filesize($ruta) > 0 ? @getimagesize($ruta) : false;
        $valida = $info !== false && $info[2] === IMAGETYPE_WEBP && $info[0] === $ancho && $info[1] === $alto
            && @imagecreatefromwebp($ruta) !== false;

        if (! $valida) {
            throw ValidationException::withMessages(['imagen' => 'No se pudo procesar la imagen; inténtalo con otra foto.']);
        }
    }

    /**
     * Valor de php.ini en bytes ('128M', '2G', '512K' o un número).
     */
    private static function bytesIni(string $valor): int
    {
        $valor = trim($valor);
        $numero = (int) $valor;

        return match (strtoupper(substr($valor, -1))) {
            'G' => $numero * 1024 ** 3,
            'M' => $numero * 1024 ** 2,
            'K' => $numero * 1024,
            default => $numero,
        };
    }
}
