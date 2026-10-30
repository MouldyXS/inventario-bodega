<?php

namespace App\Http\Controllers;

use App\Services\CargaInicialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carga inicial de existencias (Etapa 9). Solo el administrador: lo comprueban
 * la ruta y el servicio. Primero se revisa el archivo sin guardar nada y
 * después se confirma.
 */
class CargaInicialController extends Controller
{
    public function create(CargaInicialService $carga): View
    {
        return view('carga-inicial.form', ['previas' => $carga->movimientosPrevios()]);
    }

    public function plantilla(CargaInicialService $carga): StreamedResponse
    {
        return response()->streamDownload(function () use ($carga) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            foreach ($carga->plantilla() as $fila) {
                fputcsv($salida, $fila, ';', '"', '');
            }

            fclose($salida);
        }, 'plantilla-carga-inicial.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function revisar(Request $request, CargaInicialService $carga): View|RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:2048'],
        ], [
            'archivo.required' => 'Elige el archivo CSV.',
            'archivo.max' => 'El archivo es demasiado grande.',
        ]);

        $texto = (string) file_get_contents($request->file('archivo')->getRealPath());

        if (! mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }

        $lectura = $carga->leer($texto);

        if ($lectura['errores'] !== []) {
            return view('carga-inicial.form', ['previas' => $carga->movimientosPrevios(), 'erroresArchivo' => $lectura['errores']]);
        }

        $request->session()->put('carga_inicial', ['token' => (string) Str::uuid(), 'filas' => $lectura['filas']]);

        return view('carga-inicial.revisar', [
            'filas' => $lectura['filas'],
            'productos' => collect($lectura['filas'])->groupBy('producto_id')->count(),
            'previas' => $carga->movimientosPrevios(),
        ]);
    }

    public function store(Request $request, CargaInicialService $carga): RedirectResponse
    {
        $pendiente = $request->session()->get('carga_inicial');

        if (! is_array($pendiente)) {
            return redirect()->route('carga-inicial.create')->withErrors(['archivo' => 'No hay una carga revisada. Sube el archivo de nuevo.']);
        }

        if ($carga->movimientosPrevios() > 0 && ! $request->boolean('repetir')) {
            return redirect()->route('carga-inicial.create')->withErrors(['archivo' => 'Ya existe una carga inicial. Para repetirla, vuelve a subir el archivo y marca la casilla de confirmación.']);
        }

        $movimientos = $carga->registrar($request->user(), $pendiente['token'], $pendiente['filas'], now());
        $request->session()->forget('carga_inicial');

        return redirect()->route('historial.index')->with('status', "Carga inicial registrada: $movimientos productos, ".count($pendiente['filas']).' filas.');
    }
}
