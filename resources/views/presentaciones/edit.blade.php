@extends('layouts.app')

@section('titulo', 'Editar presentación')

@section('contenido')
    <h1 class="h3 mb-1">Editar presentación</h1>
    <p class="text-body-secondary">{{ $presentacion->producto->nombre }}</p>

    <form method="POST" action="{{ route('presentaciones.update', $presentacion) }}" class="card card-body" style="max-width: 40rem">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $presentacion->nombre) }}" maxlength="50"
                   class="form-control @error('nombre') is-invalid @enderror" required autofocus>
            @error('nombre')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @if ($presentacion->es_base)
            {{-- RF-02: la base siempre tiene factor 1 y no puede desactivarse. --}}
            <p class="text-body-secondary">Es la unidad base del producto: su factor es siempre 1 y no puede desactivarse.</p>
        @else
            <div class="mb-3">
                <label for="factor" class="form-label">Unidades base que contiene</label>
                <input type="number" id="factor" name="factor" min="1" step="1" value="{{ old('factor', $presentacion->factor) }}"
                       class="form-control @error('factor') is-invalid @enderror" required>
                @error('factor')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-3">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" id="activo" name="activo" value="1" class="form-check-input"
                       @checked(old('activo', $presentacion->activo))>
                <label for="activo" class="form-check-label">Activa</label>
            </div>
        @endif

        @error('activo')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('productos.show', $presentacion->producto_id) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
