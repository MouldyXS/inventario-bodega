<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = Str::squish((string) $request->query('q'));
        $categoriaId = $request->integer('categoria') ?: null;
        $estado = in_array($request->query('estado'), ['activos', 'inactivos', 'todos'], true)
            ? $request->query('estado')
            : 'activos';

        $productos = Producto::query()
            ->with(['categoria', 'presentacionBase'])
            ->when($busqueda !== '', fn (Builder $query) => $query->buscar($busqueda))
            ->when($categoriaId, fn (Builder $query) => $query->where('categoria_id', $categoriaId))
            ->when($estado !== 'todos', fn (Builder $query) => $query->where('activo', $estado === 'activos'))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('productos.index', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'busqueda' => $busqueda,
            'categoriaId' => $categoriaId,
            'estado' => $estado,
        ]);
    }

    public function show(Producto $producto): View
    {
        $producto->load(['categoria', 'presentaciones.codigos']);

        return view('productos.show', ['producto' => $producto]);
    }

    public function create(): View
    {
        return view('productos.form', [
            'producto' => new Producto(['controla_caducidad' => true, 'activo' => true]),
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    /**
     * RF-01: si ya existe un producto con el mismo nombre y marca, no se crea
     * hasta que el usuario confirme explícitamente que es otro producto. Así
     * un doble envío no crea un duplicado.
     */
    public function store(Request $request, CatalogoService $catalogo): RedirectResponse
    {
        $datos = $this->validar($request, creando: true);
        $nombreBase = Str::squish($datos['nombre_presentacion_base']);
        unset($datos['nombre_presentacion_base']);

        if (! $request->boolean('confirmar_duplicado')) {
            $duplicados = $catalogo->posiblesDuplicados($datos['nombre'], $datos['marca']);

            if ($duplicados->isNotEmpty()) {
                return back()->withInput()->with('duplicados', $duplicados->map->only(['id', 'nombre', 'marca'])->all());
            }
        }

        $producto = $catalogo->crearProducto($datos, $nombreBase);

        return redirect()->route('productos.show', $producto)->with('status', 'Producto creado.');
    }

    public function edit(Producto $producto): View
    {
        return view('productos.form', [
            'producto' => $producto,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Producto $producto, CatalogoService $catalogo): RedirectResponse
    {
        $catalogo->actualizarProducto($producto, $this->validar($request, creando: false));

        return redirect()->route('productos.show', $producto)->with('status', 'Producto actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, bool $creando): array
    {
        $request->merge([
            'nombre' => Str::squish((string) $request->input('nombre')),
            'marca' => Str::squish((string) $request->input('marca')) ?: null,
        ]);

        $datos = $request->validate([
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'marca' => ['nullable', 'string', 'max:100'],
            'stock_minimo' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            ...($creando ? ['nombre_presentacion_base' => ['required', 'string', 'max:50']] : []),
        ], [
            'categoria_id.required' => 'Elige una categoría.',
            'nombre.required' => 'Escribe el nombre del producto.',
            'stock_minimo.integer' => 'Las existencias mínimas deben ser un número entero.',
            'stock_minimo.min' => 'Las existencias mínimas no pueden ser negativas.',
            'nombre_presentacion_base.required' => 'Escribe el nombre de la unidad base (por ejemplo, Botella).',
        ]);

        $datos['controla_caducidad'] = $request->boolean('controla_caducidad');

        if (! $creando) {
            $datos['activo'] = $request->boolean('activo');
        }

        return $datos;
    }
}
