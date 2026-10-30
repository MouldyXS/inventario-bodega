<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CodigoBarras;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Paso 1 de recepciones y salidas: buscar el producto por nombre, marca o
 * código. Un código de barras exacto lleva directamente al formulario con su
 * presentación elegida.
 */
trait BuscaProducto
{
    private function buscarProducto(Request $request, string $ruta, string $titulo): View|RedirectResponse
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

        return view('compartido.buscar-producto', [
            'titulo' => $titulo,
            'ruta' => $ruta,
            'busqueda' => $busqueda,
            'productos' => $busqueda === '' ? collect() : Producto::where('activo', true)
                ->buscar($busqueda)
                ->with('categoria')
                ->orderBy('nombre')
                ->limit(30)
                ->get(),
        ]);
    }
}
