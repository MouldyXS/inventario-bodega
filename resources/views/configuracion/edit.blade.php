@extends('layouts.app')

@section('titulo', 'Configuración')

@section('contenido')
    <x-cabecera-pagina titulo="Configuración">Copias de seguridad, imágenes y alertas de caducidad.</x-cabecera-pagina>

    <div class="card card-body mb-4" style="max-width: 32rem">
        <h2 class="h5">Copias de seguridad</h2>
        @include('panel._estado-respaldo', ['respaldo' => $respaldo])
        <div class="form-text">Se hacen dos veces al día (13:00 y 23:30) en el disco D:, cifradas. Ver docs/RESTAURACION.md.</div>
    </div>

    {{-- Solo lectura: estado del servidor para las imágenes de productos (docs/OPERACION.md). --}}
    <div class="card card-body mb-4" style="max-width: 32rem">
        <h2 class="h5">Imágenes de productos</h2>
        <dl class="row mb-0 small">
            <dt class="col-7">Procesar imágenes (GD)</dt>
            <dd class="col-5">{{ $imagenes['gd'] ? 'Sí' : 'No' }}</dd>
            <dt class="col-7">Formato WebP</dt>
            <dd class="col-5">{{ $imagenes['webp'] ? 'Sí' : 'No' }}</dd>
            <dt class="col-7">Orientación de fotos (EXIF)</dt>
            <dd class="col-5">{{ $imagenes['exif'] ? 'Sí' : 'No (las fotos pueden verse giradas)' }}</dd>
            <dt class="col-7">Subida máxima / formulario</dt>
            <dd class="col-5">{{ $imagenes['subida_mb'] }} MB / {{ $imagenes['post_mb'] }} MB</dd>
            <dt class="col-7">Memoria por petición</dt>
            <dd class="col-5">{{ $imagenes['memoria'] }}</dd>
        </dl>
        @if (! $imagenes['webp'] || ! $imagenes['subida_suficiente'])
            <div class="alert alert-warning small mt-3 mb-0">
                Para subir imágenes hace falta GD con WebP, una subida máxima de 10 MB y un formulario de 12 MB.
                Ver «Imágenes de productos» en docs/OPERACION.md.
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('configuracion.update') }}" class="card card-body" style="max-width: 32rem">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="dias_alerta_caducidad" class="form-label">Periodo de alerta de caducidad</label>
            <div class="input-group">
                <input type="number" id="dias_alerta_caducidad" name="dias_alerta_caducidad" min="{{ $minimo }}" max="{{ $maximo }}" step="1"
                       inputmode="numeric" value="{{ old('dias_alerta_caducidad', $diasAlerta) }}" required
                       class="form-control @error('dias_alerta_caducidad') is-invalid @enderror">
                <span class="input-group-text">días</span>
                @error('dias_alerta_caducidad')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">
                Un lote pasa a «próximo a caducar» cuando faltan este número de días o menos para su caducidad.
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
@endsection
