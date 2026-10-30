<?php

namespace App\Http\Controllers;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
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
 * Ajuste por cantidad contada (Requerimientos RF-09, decisión G2). Requiere
 * ajustar-inventario: lo comprueban la ruta y el servicio.
 */
class AjusteController extends Controller
{
    public function create(Request $request, Producto $producto, ExistenciasService $existencias): View
    {
        $producto->load('presentaciones');

        return view('ajustes.form', [
            'producto' => $producto,
            'lotes' => $existencias->lotes($producto, $request->boolean('agotados')),
            'porLote' => $existencias->porLoteYPresentacion($producto)->groupBy('lote_id'),
            'presentaciones' => $producto->presentaciones,
            'incluirAgotados' => $request->boolean('agotados'),
            'motivos' => MotivoMovimiento::paraTipo(TipoMovimiento::Ajuste),
            'token' => old('token', (string) Str::uuid()),
        ]);
    }

    public function store(Request $request, Producto $producto, InventarioService $inventario): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'uuid'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', Rule::enum(MotivoMovimiento::class)->only(MotivoMovimiento::paraTipo(TipoMovimiento::Ajuste))],
            'observacion' => ['required', 'string', 'max:2000'],
            'conteos' => ['required', 'array'],
            'conteos.*.lote_id' => ['required', 'integer'],
            'conteos.*.presentacion_id' => ['required', 'integer'],
            'conteos.*.contado' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [
            'observacion.required' => 'Explica el motivo del ajuste en la observación.',
            'conteos.*.contado.integer' => 'La cantidad contada debe ser un número entero.',
            'conteos.*.contado.min' => 'La cantidad contada no puede ser negativa.',
        ]);

        // Solo cuentan las filas en las que se escribió una cantidad contada. Se
        // filtra aquí, sin tocar la entrada, para que tras un error cada valor
        // vuelva a su fila.
        $conteos = array_values(array_filter($datos['conteos'], fn (array $conteo) => filled($conteo['contado'] ?? null)));

        if ($conteos === []) {
            return back()->withInput()->withErrors(['conteos' => 'Escribe la cantidad contada en al menos una fila.']);
        }

        $resultado = $inventario->registrarAjuste(
            $request->user(),
            $datos['token'],
            $producto,
            Carbon::parse($datos['fecha']),
            MotivoMovimiento::from($datos['motivo']),
            $conteos,
            $datos['observacion'],
        );

        return redirect()->route('movimientos.show', $resultado->movimiento)->with('status', $resultado->reenvio
            ? 'Este ajuste ya estaba registrado: no se registró de nuevo.'
            : 'Ajuste registrado.');
    }
}
