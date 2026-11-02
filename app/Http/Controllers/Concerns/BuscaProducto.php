<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CodigoBarras;
use App\Models\Producto;
use App\Services\ExistenciasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Paso 1 de recepciones y salidas: elegir el producto de la lista (se filtra al
 * escribir en el navegador) o buscarlo por nombre, marca o código. Un código de
 * barras exacto lleva directamente al formulario con su presentación elegida.
 */
trait BuscaProducto
{
    /** Hasta este número de productos activos se imprime la lista completa; por encima, solo se lista al buscar. */
    private const MAXIMO_LISTA_COMPLETA = 500;

    private function buscarProducto(Request $request, string $ruta, string $titulo, bool $conExistencias = false): View|RedirectResponse
    {
        $busqueda = Str::squish((string) $request->query('q'));

        if ($busqueda !== '') {
            $codigo = CodigoBarras::where('codigo', $busqueda)
                ->whereHas('presentacion', fn ($q) => $q->where('activo', true)->whereHas('producto', fn ($p) => $p->where('activo', true)))
                ->with('presentacion')
                ->first();

            if ($codigo !== null) {
                return redirect()->route($ruta, [
                    'producto' => $codigo->presentacion->producto_id,
                    'presentacion' => $codigo->presentacion_id,
                ]);
            }
        }

        // Retirar muestra además la existencia de cada producto (solo informativa).
        $consulta = fn () => ($conExistencias ? app(ExistenciasService::class)->consultaProductos() : Producto::query())
            ->where('productos.activo', true)
            ->with($conExistencias ? ['categoria', 'presentacionBase'] : ['categoria'])
            ->orderBy('productos.nombre');

        $totalActivos = Producto::where('activo', true)->count();
        $listaCompleta = $totalActivos <= self::MAXIMO_LISTA_COMPLETA;

        // Con lista completa se imprimen todos y los que no coinciden con la búsqueda salen ocultos
        // (sin JavaScript queda el resultado de siempre; con JavaScript se filtra al escribir).
        $coincidentes = null;

        if ($listaCompleta) {
            $productos = $consulta()->get();
            $coincidentes = $busqueda === '' ? null : $consulta()->buscar($busqueda)->pluck('productos.id')->all();
        } else {
            $productos = $busqueda === '' ? collect() : $consulta()->buscar($busqueda)->limit(30)->get();
        }

        return view('compartido.buscar-producto', [
            'titulo' => $titulo,
            'ruta' => $ruta,
            'busqueda' => $busqueda,
            'productos' => $productos,
            'coincidentes' => $coincidentes,
            'listaCompleta' => $listaCompleta,
            'conExistencias' => $conExistencias,
        ]);
    }
}
