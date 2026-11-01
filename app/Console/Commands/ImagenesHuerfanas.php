<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos de imagen sin producto que los use: pueden quedar tras restaurar una
 * base más antigua que los archivos. Por defecto solo los lista.
 */
#[Signature('inventario:imagenes-huerfanas {--borrar : Borra los archivos listados}')]
#[Description('Lista (o borra con --borrar) las imágenes de productos que ninguna fila referencia')]
class ImagenesHuerfanas extends Command
{
    public function handle(): int
    {
        $disco = Storage::disk('local');
        $vigentes = Producto::whereNotNull('imagen')->pluck('imagen', 'id');

        $referenciadas = [];
        foreach ($vigentes as $id => $version) {
            $referenciadas[CatalogoService::rutaImagen($id, $version, 'm')] = true;
            $referenciadas[CatalogoService::rutaImagen($id, $version, 't')] = true;
        }

        $huerfanas = array_values(array_filter($disco->allFiles('productos'), fn (string $ruta) => ! isset($referenciadas[$ruta])));

        if ($huerfanas === []) {
            $this->info('No hay imágenes huérfanas.');

            return self::SUCCESS;
        }

        foreach ($huerfanas as $ruta) {
            $this->line($ruta);
        }

        if (! $this->option('borrar')) {
            $this->warn(count($huerfanas).' imágenes huérfanas. Para borrarlas: php artisan inventario:imagenes-huerfanas --borrar');

            return self::SUCCESS;
        }

        $disco->delete($huerfanas);

        // Carpetas de producto que quedaron vacías.
        foreach ($disco->directories('productos') as $carpeta) {
            if ($disco->allFiles($carpeta) === []) {
                $disco->deleteDirectory($carpeta);
            }
        }

        $this->info(count($huerfanas).' imágenes huérfanas borradas.');

        return self::SUCCESS;
    }
}
