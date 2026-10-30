@extends('layouts.app')

@section('titulo', 'Configuración')

@section('contenido')
    <h1 class="h3 mb-3">Configuración</h1>

    <div class="card card-body mb-4" style="max-width: 32rem">
        <h2 class="h5">Copias de seguridad</h2>
        @include('panel._estado-respaldo', ['respaldo' => $respaldo])
        <div class="form-text">Se hacen dos veces al día (13:00 y 23:30) en el disco D:, cifradas. Ver docs/RESTAURACION.md.</div>
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
