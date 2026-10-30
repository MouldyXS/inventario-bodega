<?php

namespace App\Http\Controllers;

use App\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cada usuario cambia su propia contraseña (Requerimientos §9.10, decisión K4).
 */
class CuentaController extends Controller
{
    public function edit(): View
    {
        return view('cuenta.edit');
    }

    public function update(Request $request, UsuarioService $usuarios): RedirectResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'password_actual.required' => 'Escribe tu contraseña actual.',
            'password.required' => 'Escribe la contraseña nueva.',
            'password.min' => 'La contraseña nueva debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
        ]);

        $usuarios->cambiarContrasena($request->user(), $datos['password_actual'], $datos['password']);
        $request->session()->regenerate();

        return redirect()->route('cuenta.edit')->with('status', 'Contraseña cambiada.');
    }
}
