@extends('layouts.app')

@section('titulo', 'Carga inicial')

@section('contenido')
    <x-cabecera-pagina titulo="Carga inicial de existencias">
        Registra las existencias contadas como entradas con motivo «Carga inicial». Los productos y las presentaciones deben existir en el catálogo.
    </x-cabecera-pagina>

    @if ($previas > 0)
        <div class="alert alert-warning">Ya hay {{ $previas }} movimientos de carga inicial. Cargar de nuevo sumará existencias; hazlo solo si es lo que quieres.</div>
    @endif
    @error('archivo')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @isset($erroresArchivo)
        <div class="alert alert-danger">
            <strong>Corrige el archivo y vuelve a subirlo (no se guardó nada):</strong>
            <ul class="mb-0">
                @foreach (array_slice($erroresArchivo, 0, 50) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            @if (count($erroresArchivo) > 50)
                <div class="mt-1">… y {{ count($erroresArchivo) - 50 }} errores más.</div>
            @endif
        </div>
    @endisset

    <div class="card mb-3" style="max-width: 48rem">
        <div class="card-body">
            <h2 class="h6">1. Plantilla de conteo</h2>
            <p class="small mb-2">
                Una fila por cada presentación activa. Imprímela para contar en la bodega o complétala en Excel.
                Columnas: producto, presentacion, cantidad, lote, caducidad (AAAA-MM-DD o DD/MM/AAAA; obligatoria si el producto controla caducidad) y costo (opcional, por unidad de la presentación).
                Si un producto tiene varios lotes, repite su fila una vez por lote.
            </p>
            <a href="{{ route('carga-inicial.plantilla') }}" class="btn btn-outline-primary">Descargar plantilla (CSV)</a>
        </div>
    </div>

    <form method="POST" action="{{ route('carga-inicial.revisar') }}" enctype="multipart/form-data" class="card" style="max-width: 48rem">
        @csrf
        <div class="card-body">
            <h2 class="h6">2. Subir el archivo completado</h2>
            <p class="small mb-2">Se revisa primero sin guardar nada.</p>
            <input type="file" name="archivo" accept=".csv,.txt" class="form-control mb-3" required>
            <button type="submit" class="btn btn-primary btn-lg">Revisar archivo</button>
        </div>
    </form>
@endsection
