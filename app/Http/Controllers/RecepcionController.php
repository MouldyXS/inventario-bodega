<?php

namespace App\Http\Controllers;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Http\Controllers\Concerns\BuscaProducto;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Recepción de mercancía (Requerimientos RF-03, §9.4). Un producto por
 * recepción, con una o varias líneas; se elige en dos pasos: buscar y registrar.
 */
class RecepcionController extends Controller
{
    use BuscaProducto;

    public function create(Request $request): View|RedirectResponse
    {
        $producto = $request->filled('producto')
            ? Producto::where('activo', true)->with('presentaciones')->findOrFail($request->integer('producto'))
            : null;

        if ($producto === null) {
            return $this->buscarProducto($request, 'recepciones.create', 'Nueva recepción');
        }

        return view('recepciones.form', [
            'producto' => $producto,
            'presentaciones' => $producto->presentaciones->where('activo', true)->values(),
            'presentacionInicial' => $request->integer('presentacion') ?: null,
            'motivos' => MotivoMovimiento::paraTipo(TipoMovimiento::Entrada),
            // §5.6: un token por formulario mostrado; se conserva al reintentar tras un error.
            'token' => old('token', (string) Str::uuid()),
        ]);
    }

    public function store(Request $request, InventarioService $inventario): RedirectResponse
    {
        // Las líneas añadidas en pantalla y dejadas en blanco se ignoran.
        $request->merge(['lineas' => array_values(array_filter(
            (array) $request->input('lineas', []),
            fn ($linea) => is_array($linea) && collect($linea)->except('presentacion_id')->filter(fn ($valor) => filled($valor))->isNotEmpty(),
        ))]);

        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('activo', true)],
            'token' => ['required', 'uuid'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', Rule::enum(MotivoMovimiento::class)->only(MotivoMovimiento::paraTipo(TipoMovimiento::Entrada))],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.presentacion_id' => ['required', 'integer'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lineas.*.codigo_lote' => ['nullable', 'string', 'max:50'],
            'lineas.*.fecha_caducidad' => ['nullable', 'date_format:Y-m-d'],
            'lineas.*.costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'lineas.required' => 'Añade al menos una línea con su cantidad.',
            'lineas.*.cantidad.required' => 'Indica la cantidad.',
            'lineas.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser mayor que cero.',
            'lineas.*.fecha_caducidad.date_format' => 'La fecha de caducidad no es válida.',
            'lineas.*.costo_unitario.numeric' => 'El costo debe ser un número.',
            'lineas.*.costo_unitario.min' => 'El costo no puede ser negativo.',
        ]);

        $resultado = $inventario->registrarEntrada(
            $request->user(),
            $datos['token'],
            Producto::findOrFail($datos['producto_id']),
            Carbon::parse($datos['fecha']),
            MotivoMovimiento::from($datos['motivo']),
            $datos['lineas'],
            $datos['observacion'] ?? null,
        );

        return redirect()->route('movimientos.show', $resultado->movimiento)->with('status', $resultado->reenvio
            ? 'Esta recepción ya estaba registrada: no se registró de nuevo.'
            : 'Recepción registrada.');
    }
}
