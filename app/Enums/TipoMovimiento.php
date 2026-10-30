<?php

namespace App\Enums;

/**
 * Requerimientos §5.2 y §5.5.
 */
enum TipoMovimiento: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Conversion = 'conversion';
    case Ajuste = 'ajuste';
    case Reversion = 'reversion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Conversion => 'Conversión',
            self::Ajuste => 'Ajuste',
            self::Reversion => 'Reversión',
        };
    }
}
