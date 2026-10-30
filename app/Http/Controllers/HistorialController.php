<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimiento;
use App\Models\DetalleMovimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Historial de movimientos (Requerimientos RF-09, §9.8): una fila por línea
 * de movimiento, del más reciente al más antiguo.
 */
class HistorialController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = [
            'tipo' => TipoMovimiento::tryFrom((string) $request->query('tipo')),
            'q' => Str::squish((string) $request->query('q')),
            'usuario' => $request->integer('usuario') ?: null,
            'desde' => $this->fecha($request->query('desde')),
            'hasta' => $this->fecha($request->query('hasta')),
        ];

        $detalles = DetalleMovimiento::query()
            ->join('movimientos', 'movimientos.id', '=', 'detalles_movimiento.movimiento_id')
            ->select('detalles_movimiento.*')
            ->with(['movimiento.usuario', 'producto', 'lote', 'presentacion'])
            ->when($filtros['tipo'], fn (Builder $q, TipoMovimiento $tipo) => $q->where('movimientos.tipo', $tipo->value))
            ->when($filtros['usuario'], fn (Builder $q, int $usuario) => $q->where('movimientos.usuario_id', $usuario))
            ->when($filtros['desde'], fn (Builder $q, string $desde) => $q->where('movimientos.fecha', '>=', $desde.' 00:00:00'))
            ->when($filtros['hasta'], fn (Builder $q, string $hasta) => $q->where('movimientos.fecha', '<=', $hasta.' 23:59:59'))
            ->when($filtros['q'] !== '', fn (Builder $q) => $q->whereHas('producto', fn (Builder $p) => $p->buscar($filtros['q'])))
            ->orderByDesc('movimientos.fecha')
            ->orderByDesc('movimientos.id')
            ->orderBy('detalles_movimiento.id')
            ->paginate(50)
            ->withQueryString();

        return view('historial.index', [
            'detalles' => $detalles,
            'filtros' => $filtros,
            'tipos' => TipoMovimiento::cases(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function fecha(mixed $valor): ?string
    {
        $valor = (string) $valor;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))
            ? $valor
            : null;
    }
}
