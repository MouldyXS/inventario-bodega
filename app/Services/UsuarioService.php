<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de usuarios (Requerimientos §9.10, §2.3). Solo el administrador.
 * El rol, los permisos especiales y `activo` solo se asignan aquí.
 *
 * Protecciones contra quedarse sin acceso: nadie se desactiva ni se quita el
 * rol de administrador a sí mismo, y siempre queda al menos un administrador
 * activo (comprobado bajo bloqueo, para que dos administradores no se quiten
 * el acceso mutuamente a la vez).
 */
class UsuarioService
{
    /**
     * @param  array{name: string, email: string, password: string, rol: string, puede_gestionar_catalogo?: bool, puede_ajustar_inventario?: bool, puede_revertir?: bool}  $datos
     */
    public function crear(User $actor, array $datos): User
    {
        Gate::forUser($actor)->authorize('administrar');

        $usuario = new User(['name' => $datos['name'], 'email' => $datos['email'], 'password' => $datos['password']]);
        $this->asignarRolYPermisos($usuario, $datos);
        $usuario->activo = true;
        $usuario->save();

        return $usuario;
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, rol: string, activo: bool, puede_gestionar_catalogo?: bool, puede_ajustar_inventario?: bool, puede_revertir?: bool}  $datos
     */
    public function actualizar(User $actor, User $usuario, array $datos): User
    {
        Gate::forUser($actor)->authorize('administrar');

        return DB::transaction(function () use ($actor, $usuario, $datos) {
            // Bloquea a los administradores activos antes de decidir.
            $administradores = User::where('rol', Rol::Admin->value)->where('activo', true)->orderBy('id')->lockForUpdate()->get();
            $actual = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();

            $dejaDeSerAdminActivo = $actual->esAdmin() && $actual->activo
                && (Rol::from($datos['rol']) !== Rol::Admin || ! $datos['activo']);

            if ($actual->id === $actor->id && $dejaDeSerAdminActivo) {
                throw ValidationException::withMessages([
                    $datos['activo'] ? 'rol' : 'activo' => 'No puedes quitarte a ti mismo el acceso de administrador. Pídeselo a otro administrador.',
                ]);
            }

            if ($dejaDeSerAdminActivo && $administradores->where('id', '!=', $actual->id)->isEmpty()) {
                throw ValidationException::withMessages([
                    $datos['activo'] ? 'rol' : 'activo' => 'Debe quedar al menos un administrador activo.',
                ]);
            }

            $actual->name = $datos['name'];
            $actual->email = $datos['email'];

            if (filled($datos['password'] ?? null)) {
                $actual->password = $datos['password'];
            }

            $this->asignarRolYPermisos($actual, $datos);
            $actual->activo = $datos['activo'];
            $actual->save();

            // Una contraseña nueva o la desactivación cierran sus sesiones abiertas.
            if (filled($datos['password'] ?? null) || ! $actual->activo) {
                DB::table('sessions')->where('user_id', $actual->id)->delete();
            }

            return $actual;
        });
    }

    /**
     * Cada usuario cambia su propia contraseña con la actual (§9.10, K4).
     */
    public function cambiarContrasena(User $usuario, string $actual, string $nueva): void
    {
        if (! Hash::check($actual, $usuario->password)) {
            throw ValidationException::withMessages(['password_actual' => 'La contraseña actual no es correcta.']);
        }

        $usuario->password = $nueva;
        $usuario->save();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function asignarRolYPermisos(User $usuario, array $datos): void
    {
        $usuario->rol = Rol::from($datos['rol']);
        // El administrador tiene implícitamente los tres permisos (§2.3).
        $esEncargado = $usuario->rol === Rol::Encargado;
        $usuario->puede_gestionar_catalogo = $esEncargado && ! empty($datos['puede_gestionar_catalogo']);
        $usuario->puede_ajustar_inventario = $esEncargado && ! empty($datos['puede_ajustar_inventario']);
        $usuario->puede_revertir = $esEncargado && ! empty($datos['puede_revertir']);
    }
}
