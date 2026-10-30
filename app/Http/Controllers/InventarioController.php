<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\ConfiguracionService;
use App\Services\ExistenciasService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Consulta de existencias (Requerimientos §9.3). Todo se calcula a partir del
 * historial de movimientos (§5.4).
 */
class InventarioController extends Controller
{
    public function index(Request $request, ExistenciasService $existencias): View
    {
        $busqueda = Str::squish((string) $request->query('q'));
        $categoriaId = $request->integer('categoria') ?: null;
        $estado = in_array($request->query('estado'), ['activos', 'inactivos', 'todos'], true)
            ? $request->query('estado')
            : 'activos';
        // Decisión I3: el indicador "Bajo el mínimo" del panel lleva aquí.
        $bajoMinimo = $request->boolean('bajo_minimo');

        $productos = ($bajoMinimo ? $existencias->consultaBajoMinimo() : $existencias->consultaProductos())
            ->with(['categoria', 'presentacionBase'])
            ->when($busqueda !== '', fn (Builder $query) => $query->buscar($busqueda))
            ->when($categoriaId, fn (Builder $query) => $query->where('productos.categoria_id', $categoriaId))
            ->when($estado !== 'todos', fn (Builder $query) => $query->where('productos.activo', $estado === 'activos'))
            ->orderBy('productos.nombre')
            ->paginate(25)
            ->withQueryString();

        return view('inventario.index', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'busqueda' => $busqueda,
            'categoriaId' => $categoriaId,
            'estado' => $estado,
            'bajoMinimo' => $bajoMinimo,
        ]);
    }

    public function show(Request $request, Producto $producto, ExistenciasService $existencias, ConfiguracionService $configuracion): View
    {
        $producto->load(['categoria', 'presentaciones']);
        $incluirAgotados = $request->boolean('agotados');

        $lotes = $existencias->lotes($producto, $incluirAgotados);
        $porLote = $existencias->porLoteYPresentacion($producto)->groupBy('lote_id');
        $totales = $existencias->consultaProductos()->whereKey($producto->id)->sole();

        return view('inventario.show', [
            'producto' => $producto,
            'lotes' => $lotes,
            'porLote' => $porLote,
            'presentaciones' => $producto->presentaciones->keyBy('id'),
            'base' => $producto->presentaciones->firstWhere('es_base', true),
            'existenciaFisica' => $totales->existencia_fisica,
            'existenciaDisponible' => $totales->existencia_disponible,
            'existencias' => $existencias,
            'diasAlerta' => $configuracion->diasAlertaCaducidad(),
            'incluirAgotados' => $incluirAgotados,
        ]);
    }
}
