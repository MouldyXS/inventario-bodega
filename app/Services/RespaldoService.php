<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Estado de las copias de seguridad (Requerimientos RNF-05, decisión K3). Lee
 * el JSON que escribe scripts/respaldo.ps1; la aplicación nunca lo modifica.
 */
class RespaldoService
{
    /**
     * @return array{
     *     instalado: bool,
     *     ultimo_ok: CarbonImmutable|null,
     *     ultimo_intento: CarbonImmutable|null,
     *     ultimo_intento_ok: bool,
     *     error: string|null,
     *     archivo: string|null,
     *     bytes: int,
     *     antiguedad_horas: int|null,
     *     atrasado: bool,
     * }
     */
    public function estado(): array
    {
        $datos = $this->leer();

        $ultimoOk = $this->fecha($datos['ultimo_ok'] ?? null);
        $minutos = $ultimoOk ? $ultimoOk->diffInMinutes(now(), true) : null;
        $antiguedad = $minutos === null ? null : (int) floor($minutos / 60);

        return [
            'instalado' => $datos !== null,
            'ultimo_ok' => $ultimoOk,
            'ultimo_intento' => $this->fecha($datos['ultimo_intento'] ?? null),
            'ultimo_intento_ok' => (bool) ($datos['ok'] ?? false),
            'error' => filled($datos['error'] ?? null) ? (string) $datos['error'] : null,
            'archivo' => filled($datos['archivo'] ?? null) ? (string) $datos['archivo'] : null,
            'bytes' => (int) ($datos['bytes'] ?? 0),
            'antiguedad_horas' => $antiguedad,
            // Sin copia correcta nunca, o con más de N horas: hay que avisar.
            'atrasado' => $minutos === null || $minutos > config('inventario.respaldo.horas_aviso') * 60,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leer(): ?array
    {
        $ruta = config('inventario.respaldo.archivo_estado');

        if (! is_file($ruta)) {
            return null;
        }

        // PowerShell 5.1 puede escribir un BOM; se ignora.
        $datos = json_decode(ltrim((string) file_get_contents($ruta), "\xEF\xBB\xBF"), true);

        return is_array($datos) ? $datos : null;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $valor)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
