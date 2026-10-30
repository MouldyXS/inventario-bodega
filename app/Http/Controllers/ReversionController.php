<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reversión de un movimiento (Requerimientos RF-09). Requiere revertir: lo
 * comprueban la ruta y el servicio.
 */
class ReversionController extends Controller
{
    public function store(Request $request, Movimiento $movimiento, InventarioService $inventario): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'uuid'],
            'observacion' => ['required', 'string', 'max:2000'],
        ], [
            'observacion.required' => 'Explica por qué se revierte el movimiento.',
        ]);

        $resultado = $inventario->revertir($request->user(), $datos['token'], $movimiento, $datos['observacion']);

        return redirect()->route('movimientos.show', $resultado->movimiento)->with('status', $resultado->reenvio
            ? 'Esta reversión ya estaba registrada: no se registró de nuevo.'
            : 'Movimiento revertido.');
    }
}
