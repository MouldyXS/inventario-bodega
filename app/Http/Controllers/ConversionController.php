<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Services\ExistenciasService;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Conversiones dentro de un lote: abrir cajas o reempaquetar (Requerimientos RF-05, §9.6).
 */
class ConversionController extends Controller
{
    public function create(Request $request, Lote $lote, ExistenciasService $existencias): View
    {
        $lote->load('producto.presentaciones');
        $enLote = $existencias->porLoteYPresentacion($lote->producto)->where('lote_id', $lote->id)->pluck('cantidad', 'presentacion_id');
        $presentaciones = $lote->producto->presentaciones;

        return view('conversiones.form', [
            'lote' => $lote,
            'producto' => $lote->producto,
            'presentaciones' => $presentaciones,
            'enLote' => $enLote,
            // Por defecto: abrir la presentación más grande que tenga existencias, hacia la base.
            'origenInicial' => $presentaciones->filter(fn ($p) => ($enLote[$p->id] ?? 0) > 0)->sortByDesc('factor')->first()?->id,
            'destinoInicial' => $presentaciones->firstWhere('es_base', true)?->id,
            'volverASalida' => $request->query('volver') === 'salida',
            'token' => old('token', (string) Str::uuid()),
        ]);
    }

    public function store(Request $request, Lote $lote, InventarioService $inventario): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'uuid'],
            'presentacion_origen_id' => ['required', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'presentacion_destino_id' => ['required', 'integer'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'cantidad.required' => 'Indica la cantidad que se convierte.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser mayor que cero.',
        ]);

        $resultado = $inventario->registrarConversion(
            $request->user(),
            $datos['token'],
            $lote,
            now(),
            (int) $datos['presentacion_origen_id'],
            (int) $datos['cantidad'],
            (int) $datos['presentacion_destino_id'],
            $datos['observacion'] ?? null,
        );

        $mensaje = $resultado->reenvio ? 'Esta conversión ya estaba registrada: no se registró de nuevo.' : 'Conversión registrada.';

        // Atajo "abrir y retirar" (decisión G1): de vuelta a la salida del mismo producto.
        if ($request->boolean('volver_a_salida')) {
            return redirect()->route('salidas.create', ['producto' => $lote->producto_id])->with('status', $mensaje);
        }

        return redirect()->route('inventario.show', $lote->producto_id)->with('status', $mensaje);
    }
}
