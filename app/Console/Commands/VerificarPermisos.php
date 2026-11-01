<?php

namespace App\Console\Commands;

use App\Services\CatalogoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Comprueba los privilegios de la cuenta MySQL con la que se ejecuta la
 * aplicación (Requerimientos §6.5, §5.6 y Caso 26). No modifica datos: las
 * sentencias de escritura llevan WHERE 1 = 0 y MySQL comprueba el privilegio
 * antes de ejecutarlas.
 */
#[Signature('inventario:verificar-permisos')]
#[Description('Comprueba que la cuenta de la aplicación no puede modificar ni borrar el historial')]
class VerificarPermisos extends Command
{
    private const SIN_PRIVILEGIO = 1142;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cuenta = DB::selectOne('SELECT CURRENT_USER() AS cuenta')->cuenta;
        $this->line("Cuenta: {$cuenta}");

        $comprobaciones = [
            ['Aislamiento READ-COMMITTED (§5.6)', DB::selectOne('SELECT @@transaction_isolation AS nivel')->nivel === 'READ-COMMITTED'],
            ['No puede modificar movimientos (Caso 26)', $this->denegada('UPDATE movimientos SET observacion = observacion WHERE 1 = 0')],
            ['No puede borrar movimientos (Caso 26)', $this->denegada('DELETE FROM movimientos WHERE 1 = 0')],
            ['No puede modificar detalles_movimiento (Caso 26)', $this->denegada('UPDATE detalles_movimiento SET cantidad = cantidad WHERE 1 = 0')],
            ['No puede borrar detalles_movimiento (Caso 26)', $this->denegada('DELETE FROM detalles_movimiento WHERE 1 = 0')],
            ['Puede bloquear lotes con FOR UPDATE (§5.6)', ! $this->denegada('SELECT id FROM lotes WHERE 1 = 0 FOR UPDATE', bloqueo: true)],
        ];

        $this->table(['Comprobación', 'Resultado'], array_map(
            fn (array $c) => [$c[0], $c[1] ? 'Correcto' : 'FALLA'],
            $comprobaciones,
        ));

        // §5.6: solo informativo, para documentar qué exige MySQL para FOR UPDATE.
        $forUpdateMovimientos = $this->denegada('SELECT id FROM movimientos WHERE 1 = 0 FOR UPDATE', bloqueo: true);
        $this->line('Información: SELECT … FOR UPDATE sobre movimientos (solo SELECT e INSERT) '
            .($forUpdateMovimientos ? 'se deniega.' : 'se permite.'));

        // Imágenes de productos: informativo, no cambia el resultado (lo que ve la consola;
        // lo que ve Apache se muestra en Configuración).
        $imagenes = app(CatalogoService::class)->estadoImagenes();
        $this->line(sprintf(
            'Imágenes: GD %s, WebP %s, EXIF %s, subida %s MB, formulario %s MB, memoria %s.',
            $imagenes['gd'] ? 'sí' : 'NO', $imagenes['webp'] ? 'sí' : 'NO', $imagenes['exif'] ? 'sí' : 'NO',
            $imagenes['subida_mb'], $imagenes['post_mb'], $imagenes['memoria'],
        ));

        if (! $imagenes['webp'] || ! $imagenes['subida_suficiente']) {
            $this->warn('Aviso: la subida de imágenes no está lista (docs/OPERACION.md, «Imágenes de productos»).');
        }

        if (in_array(false, array_column($comprobaciones, 1), true)) {
            $this->error('La cuenta no cumple §6.5. Revisa database/sql/02_permisos_app.sql.');

            return self::FAILURE;
        }

        $this->info('La cuenta de la aplicación cumple §6.5.');

        return self::SUCCESS;
    }

    /**
     * Ejecuta la sentencia y devuelve true si MySQL la rechaza por falta de privilegios.
     */
    private function denegada(string $sql, bool $bloqueo = false): bool
    {
        try {
            DB::transaction(fn () => $bloqueo ? DB::select($sql) : DB::statement($sql));

            return false;
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === self::SIN_PRIVILEGIO) {
                return true;
            }

            throw $e;
        }
    }
}
