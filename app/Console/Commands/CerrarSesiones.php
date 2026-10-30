<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cierra sesiones abiertas (Requerimientos RNF-03): si se pierde la tablet,
 * además de retirarla de la red Tailscale se invalidan sus sesiones.
 */
#[Signature('inventario:cerrar-sesiones {correo? : Solo las sesiones de este usuario; sin él, todas}')]
#[Description('Cierra todas las sesiones abiertas, o las de un usuario')]
class CerrarSesiones extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $consulta = DB::table('sessions');

        if ($correo = $this->argument('correo')) {
            $usuario = User::where('email', $correo)->first();

            if ($usuario === null) {
                $this->error("No existe un usuario con el correo {$correo}.");

                return self::FAILURE;
            }

            $consulta->where('user_id', $usuario->id);
        }

        $cerradas = $consulta->delete();
        $this->info("Sesiones cerradas: {$cerradas}.");

        return self::SUCCESS;
    }
}
