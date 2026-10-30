<?php

namespace App\Http\Controllers;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Http\Controllers\Concerns\BuscaProducto;
use App\Models\Producto;
use App\Services\ExistenciasService;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Salidas de mercancía (Requerimientos RF-06, RF-07, §9.5). Un producto por
 * salida, con una o varias líneas (lote, presentación, cantidad).
 */
class SalidaController extends Controller
{
    use BuscaProducto;

    public function create(Request $request, ExistenciasService $existencias): View|RedirectResponse
    {
        $producto = $request->filled('producto')
            ? Producto::with('presentaciones')->findOrFail($request->integer('producto'))
            : null;

        if ($producto === null) {
            return $this->buscarProducto($request, 'salidas.create', 'Nueva salida');
        }

        $lotes = $existencias->lotes($producto);

        return view('salidas.form', [
            'producto' => $producto,
            'lotes' => $lotes,
            'porLote' => $existencias->porLoteYPresentacion($producto)->groupBy('lote_id'),
            'presentaciones' => $producto->presentaciones->keyBy('id'),
            'presentacionInicial' => $request->integer('presentacion') ?: null,
            // RF-07: se recomienda el primer lote vigente en orden FEFO, sin elegirlo automáticamente.
            'recomendadoId' => $lotes->first(fn ($lote) => ! $existencias->estaVencido($lote))?->id,
            'existencias' => $existencias,
            'motivos' => MotivoMovimiento::paraTipo(TipoMovimiento::Salida),
            // Desde una alerta de caducidad se llega con el motivo "vencido" ya elegido.
            'motivoInicial' => in_array($motivo = MotivoMovimiento::tryFrom((string) $request->query('motivo')), MotivoMovimiento::paraTipo(TipoMovimiento::Salida), true)
                ? $motivo
                : MotivoMovimiento::VentaEntrega,
            'token' => old('token', (string) Str::uuid()),
        ]);
    }

    public function store(Request $request, InventarioService $inventario): RedirectResponse
    {
        // Las líneas añadidas en pantalla y dejadas sin cantidad se ignoran.
        $request->merge(['lineas' => array_values(array_filter(
            (array) $request->input('lineas', []),
            fn ($linea) => is_array($linea) && filled($linea['cantidad'] ?? null),
        ))]);

        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'token' => ['required', 'uuid'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', Rule::enum(MotivoMovimiento::class)->only(MotivoMovimiento::paraTipo(TipoMovimiento::Salida))],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.lote_id' => ['required', 'integer'],
            'lineas.*.presentacion_id' => ['required', 'integer'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'motivo.required' => 'Elige el motivo de la salida.',
            'lineas.required' => 'Añade al menos una línea con su cantidad.',
            'lineas.*.lote_id.required' => 'Elige el lote.',
            'lineas.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser mayor que cero.',
        ]);

        $resultado = $inventario->registrarSalida(
            $request->user(),
            $datos['token'],
            Producto::findOrFail($datos['producto_id']),
            Carbon::parse($datos['fecha']),
            MotivoMovimiento::from($datos['motivo']),
            $datos['lineas'],
            $datos['observacion'] ?? null,
        );

        return redirect()->route('movimientos.show', $resultado->movimiento)->with('status', $resultado->reenvio
            ? 'Esta salida ya estaba registrada: no se registró de nuevo.'
            : 'Salida registrada.');
    }
}
