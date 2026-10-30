<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * El rol, los permisos especiales y `activo` no son asignables en masa:
 * solo se modifican desde la gestión de usuarios (Requerimientos §2.3).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => Rol::class,
            'puede_gestionar_catalogo' => 'boolean',
            'puede_ajustar_inventario' => 'boolean',
            'puede_revertir' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }
}
