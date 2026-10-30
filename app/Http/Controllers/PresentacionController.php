<?php

namespace App\Http\Controllers;

use App\Models\Presentacion;
use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PresentacionController extends Controller
{
    public function store(Request $request, Producto $producto): RedirectResponse
    {
        $request->merge(['nombre' => Str::squish((string) $request->input('nombre'))]);

        $datos = $request->validateWithBag('presentacion', [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('presentaciones', 'nombre')->where('producto_id', $producto->id)],
            'factor' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], $this->mensajes());

        $producto->presentaciones()->create($datos);

        return redirect()->route('productos.show', $producto)->with('status', 'Presentación añadida.');
    }

    public function edit(Presentacion $presentacion): View
    {
        return view('presentaciones.edit', ['presentacion' => $presentacion->load('producto')]);
    }

    /**
     * El formulario de la presentación base solo envía el nombre; si llegan
     * factor o activo, CatalogoService aplica las reglas de RF-02.
     */
    public function update(Request $request, Presentacion $presentacion, CatalogoService $catalogo): RedirectResponse
    {
        $request->merge(['nombre' => Str::squish((string) $request->input('nombre'))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('presentaciones', 'nombre')
                ->where('producto_id', $presentacion->producto_id)->ignore($presentacion)],
            'factor' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
            'activo' => ['sometimes', 'boolean'],
        ], $this->mensajes());

        $catalogo->actualizarPresentacion($presentacion, $datos);

        return redirect()->route('productos.show', $presentacion->producto_id)->with('status', 'Presentación actualizada.');
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la presentación.',
            'nombre.unique' => 'Este producto ya tiene una presentación con ese nombre.',
            'factor.required' => 'Indica cuántas unidades base contiene.',
            'factor.integer' => 'El factor debe ser un número entero.',
            'factor.min' => 'El factor debe ser al menos 1.',
        ];
    }
}
