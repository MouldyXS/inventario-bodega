<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Las categorías no se borran (§6.5: la cuenta de la aplicación no tiene DELETE).
 */
class CategoriaController extends Controller
{
    public function index(): View
    {
        return view('categorias.index', [
            'categorias' => Categoria::withCount('productos')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('categorias.form', ['categoria' => new Categoria]);
    }

    public function store(Request $request): RedirectResponse
    {
        Categoria::create($this->validar($request));

        return redirect()->route('categorias.index')->with('status', 'Categoría creada.');
    }

    public function edit(Categoria $categoria): View
    {
        return view('categorias.form', ['categoria' => $categoria]);
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($this->validar($request, $categoria));

        return redirect()->route('categorias.index')->with('status', 'Categoría actualizada.');
    }

    /**
     * @return array{nombre: string, descripcion: string|null}
     */
    private function validar(Request $request, ?Categoria $categoria = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')->ignore($categoria)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], [
            'nombre.required' => 'Escribe el nombre de la categoría.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
        ]);
    }
}
