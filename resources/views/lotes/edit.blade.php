@extends('layouts.app')

@section('titulo', 'Corregir lote')

@section('contenido')
    <h1 class="h3 mb-1">Corregir datos del lote</h1>
    <p class="text-body-secondary">{{ $lote->producto->nombre }}</p>

    <form method="POST" action="{{ route('lotes.update', $lote) }}" class="card card-body" style="max-width: 32rem">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="codigo_lote" class="form-label">Código de lote <span class="text-body-secondary">(vacío = sin código)</span></label>
            <input type="text" id="codigo_lote" name="codigo_lote" maxlength="50" value="{{ old('codigo_lote', $lote->codigo_lote) }}"
                   class="form-control @error('codigo_lote') is-invalid @enderror">
            @error('codigo_lote')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @if ($lote->producto->controla_caducidad)
            <div class="mb-3">
                <label for="fecha_caducidad" class="form-label">Fecha de caducidad</label>
                <input type="date" id="fecha_caducidad" name="fecha_caducidad" required
                       value="{{ old('fecha_caducidad', $lote->fecha_caducidad?->toDateString()) }}"
                       class="form-control @error('fecha_caducidad') is-invalid @enderror">
                @error('fecha_caducidad')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <p class="small text-body-secondary">Las existencias del lote no cambian. Si la corrección coincide con otro lote del producto, se rechaza.</p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('inventario.show', $lote->producto_id) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
