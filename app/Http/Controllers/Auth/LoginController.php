<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión. No hay registro público ni recuperación de
 * contraseña por correo: el administrador crea los usuarios (Requerimientos RNF-03).
 */
class LoginController extends Controller
{
    /** Intentos fallidos permitidos por correo e IP antes de bloquear un minuto. */
    public const MAX_INTENTOS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $clave = Str::transliterate(Str::lower($credenciales['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Vuelve a intentarlo en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        // Un usuario inactivo recibe el mismo mensaje que una contraseña incorrecta.
        if (! Auth::attempt([...$credenciales, 'activo' => true])) {
            RateLimiter::hit($clave);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Borra la caché HTTP del navegador (imágenes, CSS y JS) para que otra
        // persona en la misma tablet no vea nada guardado; no toca cookies ni localStorage.
        return redirect()->route('login')->header('Clear-Site-Data', '"cache"');
    }
}
