<?php

namespace App\Services;

use App\Enums\MotivoMovimiento;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Carga inicial de existencias desde un CSV (Requerimientos Etapa 9). Solo el
 * administrador. Cada producto se registra como una entrada con motivo
 * `carga_inicial` mediante InventarioService, y todo el archivo en una sola
 * transacción: o se registra completo o no se registra nada.
 *
 * Columnas: producto, presentacion, cantidad, lote, caducidad, costo
 * (las tres primeras obligatorias). Los productos y las presentaciones deben
 * existir ya en el catálogo y se buscan por su nombre exacto.
 */
class CargaInicialService
{
    public const MAX_FILAS = 2000;

    public const ENCABEZADO = ['producto', 'presentacion', 'cantidad', 'lote', 'caducidad', 'costo'];

    public function __construct(private InventarioService $inventario) {}

    /**
     * Filas leídas y errores por fila; sin errores, las filas están listas para registrar.
     *
     * @return array{filas: list<array<string, mixed>>, errores: list<string>}
     */
    public function leer(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $primera = strtok($csv, "\n") ?: '';
        $separador = substr_count($primera, ';') >= substr_count($primera, ',') ? ';' : ',';

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $csv);
        rewind($flujo);

        $encabezado = fgetcsv($flujo, null, $separador, '"', '');
        $columnas = array_map(fn ($c) => Str::lower(Str::ascii(trim((string) $c))), $encabezado ?: []);

        foreach (['producto', 'presentacion', 'cantidad'] as $obligatoria) {
            if (! in_array($obligatoria, $columnas, true)) {
                return ['filas' => [], 'errores' => ["Falta la columna «{$obligatoria}». Descarga la plantilla para ver el formato."]];
            }
        }

        $productos = $this->catalogo();
        $filas = [];
        $errores = [];
        $numero = 1;

        while (($campos = fgetcsv($flujo, null, $separador, '"', '')) !== false) {
            $numero++;

            if ($campos === [null] || blank(implode('', $campos))) {
                continue;
            }

            if (count($filas) + count($errores) >= self::MAX_FILAS) {
                $errores[] = 'El archivo tiene más de '.self::MAX_FILAS.' filas: divídelo en varios.';
                break;
            }

            $dato = [];
            foreach ($columnas as $i => $columna) {
                $dato[$columna] = trim((string) ($campos[$i] ?? ''));
            }

            $problemas = [];
            $producto = $productos[$this->clave($dato['producto'] ?? '')] ?? null;
            $presentacionId = null;

            if ($producto === null) {
                $problemas[] = 'el producto «'.($dato['producto'] ?? '').'» no existe o está inactivo';
            } elseif ($producto === false) {
                $problemas[] = 'hay más de un producto llamado «'.$dato['producto'].'»; cambia el nombre en el catálogo';
            } else {
                $presentacionId = $producto['presentaciones'][$this->clave($dato['presentacion'] ?? '')] ?? null;

                if ($presentacionId === null) {
                    $problemas[] = 'la presentación «'.($dato['presentacion'] ?? '').'» no existe en ese producto';
                }
            }

            $cantidad = filter_var($dato['cantidad'] ?? '', FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad < 1) {
                $problemas[] = 'la cantidad debe ser un entero mayor que cero';
            }

            $caducidad = null;
            if (filled($dato['caducidad'] ?? '')) {
                $caducidad = $this->fecha($dato['caducidad']);
                if ($caducidad === null) {
                    $problemas[] = 'la caducidad debe ser AAAA-MM-DD o DD/MM/AAAA';
                }
            }

            $costo = str_replace(',', '.', $dato['costo'] ?? '');
            if ($costo !== '' && (! is_numeric($costo) || (float) $costo < 0)) {
                $problemas[] = 'el costo no es válido';
            }

            if ($problemas !== []) {
                $errores[] = "Fila $numero: ".implode('; ', $problemas).'.';

                continue;
            }

            $filas[] = [
                'fila' => $numero,
                'producto_id' => $producto['id'],
                'producto' => $producto['nombre'],
                'presentacion_id' => $presentacionId,
                'presentacion' => $dato['presentacion'],
                'cantidad' => $cantidad,
                'codigo_lote' => $dato['lote'] ?? '',
                'fecha_caducidad' => $caducidad,
                'costo_unitario' => $costo === '' ? null : $costo,
            ];
        }

        fclose($flujo);

        if ($filas === [] && $errores === []) {
            $errores[] = 'El archivo no tiene filas de datos.';
        }

        return ['filas' => $filas, 'errores' => $errores];
    }

    /**
     * @param  list<array<string, mixed>>  $filas  Salida de leer(), sin errores.
     * @return int Cantidad de movimientos registrados (uno por producto).
     */
    public function registrar(User $actor, string $token, array $filas, CarbonInterface $fecha): int
    {
        Gate::forUser($actor)->authorize('administrar');

        $porProducto = collect($filas)->groupBy('producto_id');

        return DB::transaction(function () use ($actor, $token, $fecha, $porProducto) {
            foreach ($porProducto as $productoId => $lineas) {
                $producto = Producto::findOrFail($productoId);

                try {
                    $this->inventario->registrarEntrada(
                        $actor,
                        Uuid::uuid5($token, "producto-$productoId")->toString(),
                        $producto,
                        $fecha,
                        MotivoMovimiento::CargaInicial,
                        $lineas->map(fn (array $f) => [
                            'presentacion_id' => $f['presentacion_id'],
                            'cantidad' => $f['cantidad'],
                            'codigo_lote' => $f['codigo_lote'],
                            'fecha_caducidad' => $f['fecha_caducidad'],
                            'costo_unitario' => $f['costo_unitario'],
                        ])->all(),
                        'Carga inicial',
                    );
                } catch (ValidationException $e) {
                    $mensaje = collect($e->errors())->flatten()->unique()->implode(' ');

                    throw ValidationException::withMessages(['archivo' => "{$producto->nombre}: $mensaje"]);
                }
            }

            return $porProducto->count();
        });
    }

    public function movimientosPrevios(): int
    {
        return Movimiento::where('motivo', MotivoMovimiento::CargaInicial->value)->count();
    }

    /**
     * Plantilla: una fila por cada presentación activa, con cantidad en blanco.
     *
     * @return list<list<string>>
     */
    public function plantilla(): array
    {
        $filas = [self::ENCABEZADO];

        Producto::where('activo', true)->orderBy('nombre')->with('presentaciones')->get()
            ->each(function (Producto $producto) use (&$filas) {
                foreach ($producto->presentaciones->where('activo', true) as $presentacion) {
                    $filas[] = [$producto->nombre, $presentacion->nombre, '', '', '', ''];
                }
            });

        return $filas;
    }

    /**
     * nombre normalizado => ['id', 'nombre', 'presentaciones' => [nombre normalizado => id]]; false si el nombre se repite.
     *
     * @return array<string, array<string, mixed>|false>
     */
    private function catalogo(): array
    {
        $mapa = [];

        Producto::where('activo', true)->with(['presentaciones' => fn ($q) => $q->where('activo', true)])->get()
            ->each(function (Producto $producto) use (&$mapa) {
                $clave = $this->clave($producto->nombre);

                $mapa[$clave] = array_key_exists($clave, $mapa) ? false : [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'presentaciones' => $producto->presentaciones->mapWithKeys(fn ($p) => [$this->clave($p->nombre) => $p->id])->all(),
                ];
            });

        return $mapa;
    }

    private function clave(string $texto): string
    {
        return Str::lower(Str::squish($texto));
    }

    private function fecha(string $texto): ?string
    {
        foreach (['!Y-m-d', '!d/m/Y'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat($formato, $texto);

            if ($fecha !== false && $fecha->format(ltrim($formato, '!')) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }
}
