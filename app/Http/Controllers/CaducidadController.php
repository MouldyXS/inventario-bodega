<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCaducidad;
use App\Models\Categoria;
use App\Models\Presentacion;
use App\Services\ConfiguracionService;
use App\Services\ExistenciasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lotes por estado de caducidad (Requerimientos RF-08, §9.7). Solo lotes con
 * caducidad y existencias.
 */
class CaducidadController extends Controller
{
    /** Filtro por estado => estados que incluye. */
    private const FILTROS = [
        'alertas' => [EstadoCaducidad::Vencido, EstadoCaducidad::Proximo],
        'vencido' => [EstadoCaducidad::Vencido],
        'proximo' => [EstadoCaducidad::Proximo],
        'vigente' => [EstadoCaducidad::Vigente],
        'todos' => [EstadoCaducidad::Vencido, EstadoCaducidad::Proximo, EstadoCaducidad::Vigente],
    ];

    public function index(Request $request, ExistenciasService $existencias, ConfiguracionService $configuracion): View
    {
        $diasAlerta = $configuracion->diasAlertaCaducidad();
        $filtro = array_key_exists((string) $request->query('estado'), self::FILTROS) ? (string) $request->query('estado') : 'alertas';
        $busqueda = Str::squish((string) $request->query('q'));
        $categoriaId = $request->integer('categoria') ?: null;

        $lotes = $existencias->consultaCaducidad($diasAlerta, self::FILTROS[$filtro])
            ->with('producto')
            ->when($categoriaId, fn (Builder $q) => $q->where('productos.categoria_id', $categoriaId))
            ->when($busqueda !== '', fn (Builder $q) => $q->whereHas('producto', fn (Builder $p) => $p->buscar($busqueda)))
            ->paginate(50)
            ->withQueryString();

        return view('caducidad.index', [
            'lotes' => $lotes,
            'porLote' => $existencias->porPresentacionDeLotes($lotes->pluck('id')->all()),
            'presentaciones' => Presentacion::whereIn('producto_id', $lotes->pluck('producto_id')->unique())->get()->keyBy('id'),
            'resumen' => $existencias->resumenCaducidad($diasAlerta),
            'diasAlerta' => $diasAlerta,
            'existencias' => $existencias,
            'filtro' => $filtro,
            'busqueda' => $busqueda,
            'categoriaId' => $categoriaId,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }
}
