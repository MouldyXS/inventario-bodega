<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCaducidad;
use App\Models\Movimiento;
use App\Services\ConfiguracionService;
use App\Services\ExistenciasService;
use App\Services\RespaldoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel principal (Requerimientos RF-10, §9.1).
 */
class PanelController extends Controller
{
    public function __invoke(Request $request, ExistenciasService $existencias, ConfiguracionService $configuracion, RespaldoService $respaldo): View
    {
        $diasAlerta = $configuracion->diasAlertaCaducidad();
        $estadoRespaldo = $respaldo->estado();

        return view('inicio', [
            // Decisión K3: solo el administrador ve el aviso de copia atrasada o ausente.
            'avisoRespaldo' => $request->user()->can('administrar') && $estadoRespaldo['atrasado'] ? $estadoRespaldo : null,
            'indicadores' => $existencias->indicadores($diasAlerta),
            'diasAlerta' => $diasAlerta,
            // Los más antiguos primero: llevan más tiempo vencidos.
            'vencidos' => $existencias->consultaCaducidad($diasAlerta, [EstadoCaducidad::Vencido])->with('producto')->limit(5)->get(),
            'proximos' => $existencias->consultaCaducidad($diasAlerta, [EstadoCaducidad::Proximo])->with('producto')->limit(5)->get(),
            'bajoMinimo' => $existencias->consultaBajoMinimo()->with('presentacionBase')->orderBy('productos.nombre')->limit(10)->get(),
            // Decisión I2: lo último que se registró, aunque haya ocurrido antes.
            'recientes' => Movimiento::with(['usuario', 'detalles.producto', 'detalles.presentacion'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
