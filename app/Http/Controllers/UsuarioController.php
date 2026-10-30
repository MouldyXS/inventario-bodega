<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Usuarios (Requerimientos §9.10). Solo el administrador: lo comprueban la
 * ruta y UsuarioService. Los usuarios no se borran: se desactivan.
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('usuarios.index', ['usuarios' => User::orderByDesc('activo')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new User(['rol' => Rol::Encargado, 'activo' => true])]);
    }

    public function store(Request $request, UsuarioService $usuarios): RedirectResponse
    {
        $datos = $request->validate($this->reglas() + [
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], $this->mensajes());

        $usuario = $usuarios->crear($request->user(), $datos);

        return redirect()->route('usuarios.index')->with('status', "Usuario {$usuario->email} creado.");
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.form', ['usuario' => $usuario]);
    }

    public function update(Request $request, User $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $datos = $request->validate($this->reglas($usuario) + [
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ], $this->mensajes());

        $datos['activo'] = $request->boolean('activo');
        $usuarios->actualizar($request->user(), $usuario, $datos);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?User $usuario = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'puede_gestionar_catalogo' => ['sometimes', 'boolean'],
            'puede_ajustar_inventario' => ['sometimes', 'boolean'],
            'puede_revertir' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'name.required' => 'Escribe el nombre.',
            'email.required' => 'Escribe el correo.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.required' => 'Escribe una contraseña inicial.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
