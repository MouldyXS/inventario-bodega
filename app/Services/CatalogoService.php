<?php

namespace App\Services;

use App\Models\DetalleMovimiento;
use App\Models\Lote;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del catálogo que no puede garantizar MySQL (Requerimientos §5.7).
 */
class CatalogoService
{
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
}
