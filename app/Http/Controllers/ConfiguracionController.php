<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracionService;
use App\Services\RespaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuración del sistema (Requerimientos §9.10). Solo el administrador:
 * lo comprueban la ruta y el servicio.
 */
class ConfiguracionController extends Controller
{
    public function edit(ConfiguracionService $configuracion, RespaldoService $respaldo): View
    {
        return view('configuracion.edit', [
            'diasAlerta' => $configuracion->diasAlertaCaducidad(),
            'minimo' => ConfiguracionService::DIAS_ALERTA_MINIMO,
            'maximo' => ConfiguracionService::DIAS_ALERTA_MAXIMO,
            'respaldo' => $respaldo->estado(),
        ]);
    }

    public function update(Request $request, ConfiguracionService $configuracion): RedirectResponse
    {
        $datos = $request->validate([
            'dias_alerta_caducidad' => ['required', 'integer'],
        ], [
            'dias_alerta_caducidad.required' => 'Indica el periodo de alerta en días.',
            'dias_alerta_caducidad.integer' => 'El periodo de alerta debe ser un número entero de días.',
        ]);

        $configuracion->cambiarDiasAlertaCaducidad($request->user(), (int) $datos['dias_alerta_caducidad']);

        return redirect()->route('configuracion.edit')->with('status', 'Configuración guardada.');
    }
}
