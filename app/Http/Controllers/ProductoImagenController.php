<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imagen principal del producto. Cualquier usuario autenticado la ve; solo el
 * administrador la carga, sustituye o quita (lo comprueban la ruta y el servicio).
 */
class ProductoImagenController extends Controller
{
    /**
     * La URL lleva la versión: al cambiar la imagen cambia la URL, así que cada
     * versión se puede guardar en caché sin caducar. Una versión que ya no es la
     * vigente responde 404.
     */
    public function show(Producto $producto, string $version, string $tamano): StreamedResponse
    {
        abort_unless($producto->imagen !== null && hash_equals($producto->imagen, $version), 404);

        $ruta = CatalogoService::rutaImagen($producto->id, $version, $tamano);
        abort_unless(Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=2592000, immutable',
        ]);
    }

    public function update(Request $request, Producto $producto, CatalogoService $catalogo): RedirectResponse
    {
        $request->validate([
            'imagen' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(CatalogoService::IMAGEN_MAX_KB)],
        ], [
            'imagen.required' => 'Elige una imagen.',
            'imagen.uploaded' => 'La imagen no llegó al servidor: puede superar el tamaño que admite (ver Configuración).',
            'imagen.max' => 'La imagen no puede superar 8 MB.',
            'imagen.image' => 'El archivo debe ser una imagen JPEG, PNG o WebP.',
            'imagen.mimes' => 'El archivo debe ser una imagen JPEG, PNG o WebP.',
        ]);

        $catalogo->cambiarImagen($request->user(), $producto, $request->file('imagen'));

        return redirect()->route('productos.show', $producto)->with('status', 'Imagen guardada.');
    }

    public function destroy(Request $request, Producto $producto, CatalogoService $catalogo): RedirectResponse
    {
        $catalogo->quitarImagen($request->user(), $producto);

        return redirect()->route('productos.show', $producto)->with('status', 'Imagen quitada.');
    }
}
