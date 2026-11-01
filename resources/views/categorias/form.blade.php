@extends('layouts.app')

@section('titulo', $categoria->exists ? 'Editar categoría' : 'Nueva categoría')

@section('contenido')
    <x-cabecera-pagina :titulo="$categoria->exists ? 'Editar categoría' : 'Nueva categoría'" />

    <form method="POST" action="{{ $categoria->exists ? route('categorias.update', $categoria) : route('categorias.store') }}"
          style="max-width: 40rem">
        @csrf
        @if ($categoria->exists)
            @method('PUT')
        @endif

        <div class="card card-body mb-3">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $categoria->nombre) }}" maxlength="100"
                   class="form-control @error('nombre') is-invalid @enderror" required autofocus>
            @error('nombre')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción <span class="text-body-secondary">(opcional)</span></label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="1000"
                      class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $categoria->descripcion) }}</textarea>
            @error('descripcion')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        </div>

        <div class="d-flex gap-2 barra-acciones">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
