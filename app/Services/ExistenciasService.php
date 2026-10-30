<?php

namespace App\Services;

use App\Enums\EstadoCaducidad;
use App\Models\Lote;
use App\Models\Presentacion;
use App\Models\Producto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Existencias derivadas del historial de movimientos (Requerimientos §5.4).
 * No hay tabla de saldos: todo se calcula sumando detalles_movimiento.
 */
class ExistenciasService
{
    /**
     * "Hoy" lo calcula la aplicación en America/Guayaquil, no el reloj de la
     * base de datos (RF-07).
     */
    public function hoy(): string
    {
        return now()->toDateString();
    }

    /**
     * RF-07: vencido cuando la caducidad es anterior a hoy; el día de la
     * caducidad el lote todavía es válido.
     */
    public function estaVencido(Lote $lote): bool
    {
        return $lote->fecha_caducidad !== null && $lote->fecha_caducidad->toDateString() < $this->hoy();
    }

    /**
     * Días desde hoy hasta la caducidad: 0 si vence hoy, negativo si ya venció,
     * null si el lote no tiene caducidad.
     */
    public function diasRestantes(Lote $lote): ?int
    {
        if ($lote->fecha_caducidad === null) {
            return null;
        }

        return (int) round(CarbonImmutable::parse($this->hoy())->diffInDays($lote->fecha_caducidad->copy()->startOfDay(), false));
    }

    /**
     * RF-08: vencido si la caducidad es anterior a hoy; próximo si faltan entre
     * 0 y N días; vigente si faltan más de N días. Sin caducidad, sin estado.
     */
    public function estado(Lote $lote, int $diasAlerta): ?EstadoCaducidad
    {
        $dias = $this->diasRestantes($lote);

        return match (true) {
            $dias === null => null,
            $dias < 0 => EstadoCaducidad::Vencido,
            $dias <= $diasAlerta => EstadoCaducidad::Proximo,
            default => EstadoCaducidad::Vigente,
        };
    }

    /**
     * Lotes con caducidad y existencia mayor que cero, de todos los productos,
     * en los estados pedidos y ordenados por caducidad (RF-08, §9.7). El filtro
     * se resuelve en SQL con "hoy" y el límite calculados por PHP.
     *
     * @param  list<EstadoCaducidad>  $estados
     * @return Builder<Lote>
     */
    public function consultaCaducidad(int $diasAlerta, array $estados): Builder
    {
        $hoy = $this->hoy();
        $limite = CarbonImmutable::parse($hoy)->addDays($diasAlerta)->toDateString();

        $totales = DB::table('detalles_movimiento')
            ->selectRaw('lote_id, SUM(cantidad_base) AS existencia_base')
            ->groupBy('lote_id')
            ->havingRaw('SUM(cantidad_base) > 0');

        return Lote::query()
            ->joinSub($totales, 't', 't.lote_id', '=', 'lotes.id')
            ->join('productos', 'productos.id', '=', 'lotes.producto_id')
            ->whereNotNull('lotes.fecha_caducidad')
            ->where(function (Builder $query) use ($estados, $hoy, $limite) {
                foreach ($estados as $estado) {
                    match ($estado) {
                        EstadoCaducidad::Vencido => $query->orWhere('lotes.fecha_caducidad', '<', $hoy),
                        EstadoCaducidad::Proximo => $query->orWhereBetween('lotes.fecha_caducidad', [$hoy, $limite]),
                        EstadoCaducidad::Vigente => $query->orWhere('lotes.fecha_caducidad', '>', $limite),
                    };
                }
            })
            ->select('lotes.*', 't.existencia_base')
            ->selectRaw('DATEDIFF(lotes.fecha_caducidad, ?) AS dias_restantes', [$hoy])
            ->orderBy('lotes.fecha_caducidad')
            ->orderBy('productos.nombre')
            ->orderBy('lotes.id')
            ->withCasts(['existencia_base' => 'integer', 'dias_restantes' => 'integer']);
    }

    /**
     * Cuántos lotes con existencias están vencidos y cuántos próximos a caducar.
     *
     * @return array{vencidos: int, proximos: int}
     */
    public function resumenCaducidad(int $diasAlerta): array
    {
        return [
            'vencidos' => $this->consultaCaducidad($diasAlerta, [EstadoCaducidad::Vencido])->count(),
            'proximos' => $this->consultaCaducidad($diasAlerta, [EstadoCaducidad::Proximo])->count(),
        ];
    }

    /**
     * Existencia por par (lote, presentación) de un producto, sin los pares en cero.
     *
     * @return Collection<int, object{lote_id: int, presentacion_id: int, cantidad: int}>
     */
    public function porLoteYPresentacion(Producto $producto): Collection
    {
        return DB::table('detalles_movimiento')
            ->where('producto_id', $producto->id)
            ->selectRaw('lote_id, presentacion_id, SUM(cantidad) AS cantidad')
            ->groupBy('lote_id', 'presentacion_id')
            ->havingRaw('SUM(cantidad) <> 0')
            ->get()
            ->map(fn (object $fila) => (object) [
                'lote_id' => (int) $fila->lote_id,
                'presentacion_id' => (int) $fila->presentacion_id,
                'cantidad' => (int) $fila->cantidad,
            ]);
    }

    /**
     * Existencia por presentación de varios lotes, sin los pares en cero.
     *
     * @param  list<int>  $loteIds
     * @return Collection<int, Collection<int, object{lote_id: int, presentacion_id: int, cantidad: int}>> Agrupada por lote_id.
     */
    public function porPresentacionDeLotes(array $loteIds): Collection
    {
        if ($loteIds === []) {
            return collect();
        }

        return DB::table('detalles_movimiento')
            ->whereIn('lote_id', $loteIds)
            ->selectRaw('lote_id, presentacion_id, SUM(cantidad) AS cantidad')
            ->groupBy('lote_id', 'presentacion_id')
            ->havingRaw('SUM(cantidad) <> 0')
            ->get()
            ->map(fn (object $fila) => (object) [
                'lote_id' => (int) $fila->lote_id,
                'presentacion_id' => (int) $fila->presentacion_id,
                'cantidad' => (int) $fila->cantidad,
            ])
            ->groupBy('lote_id');
    }

    /**
     * Existencia de una presentación sumando todos sus lotes.
     */
    public function dePresentacion(Presentacion $presentacion): int
    {
        return (int) DB::table('detalles_movimiento')->where('presentacion_id', $presentacion->id)->sum('cantidad');
    }

    /**
     * Lotes del producto en orden FEFO (caducidad más próxima primero, sin
     * caducidad al final, empate por antigüedad), con su existencia en unidades
     * base y las fechas de primera y última recepción (RF-04, RF-07).
     *
     * @return EloquentCollection<int, Lote>
     */
    public function lotes(Producto $producto, bool $incluirAgotados = false): EloquentCollection
    {
        $totales = DB::table('detalles_movimiento as d')
            ->join('movimientos as m', 'm.id', '=', 'd.movimiento_id')
            ->selectRaw("d.lote_id,
                SUM(d.cantidad_base) AS existencia_base,
                MIN(CASE WHEN m.tipo = 'entrada' THEN m.fecha END) AS primera_recepcion,
                MAX(CASE WHEN m.tipo = 'entrada' THEN m.fecha END) AS ultima_recepcion")
            ->groupBy('d.lote_id');

        return Lote::query()
            ->where('lotes.producto_id', $producto->id)
            ->joinSub($totales, 't', 't.lote_id', '=', 'lotes.id')
            ->select('lotes.*', 't.existencia_base', 't.primera_recepcion', 't.ultima_recepcion')
            ->when(! $incluirAgotados, fn (Builder $query) => $query->where('t.existencia_base', '<>', 0))
            ->orderByRaw('lotes.fecha_caducidad IS NULL, lotes.fecha_caducidad, lotes.id')
            ->withCasts([
                'existencia_base' => 'integer',
                'primera_recepcion' => 'datetime',
                'ultima_recepcion' => 'datetime',
            ])
            ->get();
    }

    /**
     * Productos con su existencia física (todos los lotes) y disponible (sin
     * vencidos), en unidades base (§5.4, RF-10).
     *
     * @return Builder<Producto>
     */
    public function consultaProductos(): Builder
    {
        $totales = DB::table('detalles_movimiento as d')
            ->join('lotes as l', 'l.id', '=', 'd.lote_id')
            ->selectRaw('d.producto_id,
                SUM(d.cantidad_base) AS fisica,
                SUM(CASE WHEN l.fecha_caducidad IS NULL OR l.fecha_caducidad >= ? THEN d.cantidad_base ELSE 0 END) AS disponible',
                [$this->hoy()])
            ->groupBy('d.producto_id');

        return Producto::query()
            ->leftJoinSub($totales, 'e', 'e.producto_id', '=', 'productos.id')
            ->select('productos.*')
            ->selectRaw('COALESCE(e.fisica, 0) AS existencia_fisica, COALESCE(e.disponible, 0) AS existencia_disponible')
            ->withCasts([
                'existencia_fisica' => 'integer',
                'existencia_disponible' => 'integer',
            ]);
    }

    /**
     * RF-01 y RF-10: productos activos con existencias mínimas definidas cuya
     * existencia disponible (sin vencidos) está por debajo del mínimo.
     *
     * @return Builder<Producto>
     */
    public function consultaBajoMinimo(): Builder
    {
        return $this->consultaProductos()
            ->where('productos.activo', true)
            ->whereNotNull('productos.stock_minimo')
            ->whereRaw('COALESCE(e.disponible, 0) < productos.stock_minimo');
    }

    /**
     * Indicadores del panel principal (RF-10). Ninguno suma unidades de
     * productos distintos.
     *
     * @return array{productos: int, productos_con_existencia: int, lotes_activos: int, proximos: int, vencidos: int, bajo_minimo: int}
     */
    public function indicadores(int $diasAlerta): array
    {
        $caducidad = $this->resumenCaducidad($diasAlerta);

        return [
            'productos' => Producto::where('activo', true)->count(),
            'productos_con_existencia' => $this->consultaProductos()
                ->where('productos.activo', true)
                ->whereRaw('COALESCE(e.fisica, 0) > 0')
                ->count(),
            'lotes_activos' => DB::query()
                ->fromSub(DB::table('detalles_movimiento')->select('lote_id')->groupBy('lote_id')->havingRaw('SUM(cantidad_base) > 0'), 'activos')
                ->count(),
            'proximos' => $caducidad['proximos'],
            'vencidos' => $caducidad['vencidos'],
            'bajo_minimo' => $this->consultaBajoMinimo()->count(),
        ];
    }
}
