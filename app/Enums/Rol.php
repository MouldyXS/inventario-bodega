<?php

namespace App\Enums;

/**
 * Roles base del sistema (Requerimientos §2.3).
 */
enum Rol: string
{
    case Admin = 'admin';
    case Encargado = 'encargado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Encargado => 'Encargado de bodega',
        };
    }
}
