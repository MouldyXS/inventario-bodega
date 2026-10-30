<?php

namespace App\Http\Controllers;

use App\Models\CodigoBarras;
use App\Models\Presentacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Los códigos de barras sí pueden quitarse (§6.5: DELETE permitido en codigos_barras).
 */
class CodigoBarrasController extends Controller
{
    public function store(Request $request, Presentacion $presentacion): RedirectResponse
    {
        $request->merge(['codigo' => trim((string) $request->input('codigo'))]);

        $datos = $request->validateWithBag('codigo_'.$presentacion->id, [
            'codigo' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Za-z\-]+$/', 'unique:codigos_barras,codigo'],
        ], [
            'codigo.required' => 'Escribe o escanea el código.',
            'codigo.regex' => 'El código solo puede contener letras, números y guiones.',
            'codigo.unique' => 'Ese código ya está asignado a una presentación.',
        ]);

        $presentacion->codigos()->create($datos);

        return redirect()->route('productos.show', $presentacion->producto_id)->with('status', 'Código añadido.');
    }

    public function destroy(CodigoBarras $codigo): RedirectResponse
    {
        $productoId = $codigo->presentacion->producto_id;
        $codigo->delete();

        return redirect()->route('productos.show', $productoId)->with('status', 'Código quitado.');
    }
}
