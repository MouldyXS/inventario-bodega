<?php

namespace App\Http\Controllers;

use App\Enums\TipoMovimiento;
use App\Models\Movimiento;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MovimientoController extends Controller
{
    public function show(Movimiento $movimiento): View
    {
        $movimiento->load(['usuario', 'revierte', 'reversion', 'detalles.producto', 'detalles.lote', 'detalles.presentacion']);

        return view('movimientos.show', [
            'movimiento' => $movimiento,
            // RF-09: solo se ofrece revertir lo que no es una reversión ni está revertido.
            'revertible' => $movimiento->tipo !== TipoMovimiento::Reversion && $movimiento->reversion === null,
            'token' => old('token', (string) Str::uuid()),
        ]);
    }
}
