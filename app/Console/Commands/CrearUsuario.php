<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Crea usuarios desde la consola hasta que exista la pantalla de usuarios
 * (Requerimientos §9.10, Etapa 8). La contraseña se pide sin mostrarla.
 */
#[Signature('usuarios:crear')]
#[Description('Crea un usuario administrador o encargado pidiendo sus datos por consola')]
class CrearUsuario extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $datos = [
            'nombre' => $this->ask('Nombre'),
            'email' => $this->ask('Correo'),
            'rol' => $this->choice('Rol', [Rol::Admin->value, Rol::Encargado->value], Rol::Encargado->value),
            'password' => $this->secret('Contraseña (mínimo 8 caracteres)'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
        ];

        $validador = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'rol' => ['required', Rule::enum(Rol::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $mensaje) {
                $this->error($mensaje);
            }

            return self::FAILURE;
        }

        $usuario = new User([
            'name' => $datos['nombre'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $usuario->rol = Rol::from($datos['rol']);

        if ($usuario->rol === Rol::Encargado) {
            $usuario->puede_gestionar_catalogo = $this->confirm('¿Puede gestionar el catálogo?');
            $usuario->puede_ajustar_inventario = $this->confirm('¿Puede ajustar el inventario y corregir lotes?');
            $usuario->puede_revertir = $this->confirm('¿Puede revertir movimientos?');
        }

        $usuario->save();

        $this->info("Usuario {$usuario->email} creado como {$usuario->rol->etiqueta()}.");

        return self::SUCCESS;
    }
}
