<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Configuración del sistema (Requerimientos RF-08, §9.10). Solo el
 * administrador puede cambiarla.
 */
class ConfiguracionService
{
    public const DIAS_ALERTA_CADUCIDAD = 'dias_alerta_caducidad';

    public const DIAS_ALERTA_POR_DEFECTO = 30;

    public const DIAS_ALERTA_MINIMO = 1;

    public const DIAS_ALERTA_MAXIMO = 365;

    /**
     * RF-08: días antes de la caducidad en que un lote pasa a "próximo a caducar".
     */
    public function diasAlertaCaducidad(): int
    {
        $valor = Configuracion::find(self::DIAS_ALERTA_CADUCIDAD)?->valor;

        return $valor !== null ? (int) $valor : self::DIAS_ALERTA_POR_DEFECTO;
    }

    public function cambiarDiasAlertaCaducidad(User $usuario, int $dias): void
    {
        Gate::forUser($usuario)->authorize('administrar');

        if ($dias < self::DIAS_ALERTA_MINIMO || $dias > self::DIAS_ALERTA_MAXIMO) {
            throw ValidationException::withMessages([
                self::DIAS_ALERTA_CADUCIDAD => 'El periodo de alerta debe estar entre '.self::DIAS_ALERTA_MINIMO.' y '.self::DIAS_ALERTA_MAXIMO.' días.',
            ]);
        }

        Configuracion::updateOrCreate(['clave' => self::DIAS_ALERTA_CADUCIDAD], ['valor' => (string) $dias]);
    }
}
