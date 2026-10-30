<?php

namespace App\Enums;

/**
 * Estados de caducidad de un lote (Requerimientos RF-08). Los lotes sin fecha
 * de caducidad no tienen estado.
 */
enum EstadoCaducidad: string
{
    case Vencido = 'vencido';
    case Proximo = 'proximo';
    case Vigente = 'vigente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vencido => 'Vencido',
            self::Proximo => 'Próximo a caducar',
            self::Vigente => 'Vigente',
        };
    }

    public function claseInsignia(): string
    {
        return match ($this) {
            self::Vencido => 'text-bg-danger',
            self::Proximo => 'text-bg-warning',
            self::Vigente => 'text-bg-success',
        };
    }

    public static function textoDias(int $dias): string
    {
        return match (true) {
            $dias === 0 => 'Vence hoy',
            $dias === 1 => 'Falta 1 día',
            $dias > 1 => "Faltan {$dias} días",
            $dias === -1 => 'Venció ayer',
            default => 'Venció hace '.abs($dias).' días',
        };
    }
}
