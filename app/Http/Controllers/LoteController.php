<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Corrección del código o la caducidad de un lote (Requerimientos RF-04).
 * Requiere ajustar-inventario: lo comprueban la ruta y el servicio.
 */
class LoteController extends Controller
{
    public function edit(Lote $lote): View
    {
        return view('lotes.edit', ['lote' => $lote->load('producto')]);
    }

    public function update(Request $request, Lote $lote, InventarioService $inventario): RedirectResponse
    {
        $datos = $request->validate([
            'codigo_lote' => ['nullable', 'string', 'max:50'],
            'fecha_caducidad' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'fecha_caducidad.date_format' => 'La fecha de caducidad no es válida.',
        ]);

        $inventario->corregirLote($request->user(), $lote, $datos['codigo_lote'] ?? null, $datos['fecha_caducidad'] ?? null);

        return redirect()->route('inventario.show', $lote->producto_id)->with('status', 'Datos del lote corregidos.');
    }
}
