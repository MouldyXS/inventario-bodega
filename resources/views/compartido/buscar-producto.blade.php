@extends('layouts.app')

@section('titulo', $titulo)

@section('contenido')
    <h1 class="h3 mb-3">{{ $titulo }}</h1>

    <form method="GET" action="{{ route($ruta) }}" class="row g-2 mb-4" style="max-width: 48rem">
        <div class="col-12 col-md-9">
            <label for="q" class="form-label">Producto</label>
            <div class="campo-busqueda">
                <svg class="icono" aria-hidden="true"><use href="#i-buscar"/></svg>
                <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control form-control-lg"
                       placeholder="Nombre, marca o escanea el código" autofocus>
            </div>
        </div>
        <div class="col-12 col-md-3 d-grid align-items-end">
            <button type="submit" class="btn btn-primary btn-lg mt-md-4">Buscar</button>
        </div>
    </form>

    @if ($busqueda !== '')
        @if ($productos->isEmpty())
            @include('compartido.vacio', ['mensaje' => 'No hay productos activos que coincidan con «'.$busqueda.'».', 'quitar' => route($ruta)])
        @else
            <div class="list-group" style="max-width: 48rem">
                @foreach ($productos as $producto)
                    <a href="{{ route($ruta, ['producto' => $producto->id]) }}"
                       class="list-group-item list-group-item-action resultado-producto">
                        @include('compartido.miniatura', ['producto' => $producto, 'lado' => 52])
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-semibold">{{ $producto->nombre }}</span>
                            <span class="d-block small text-body-secondary">{{ $producto->marca ?: 'Sin marca' }} · {{ $producto->categoria->nombre }}</span>
                        </span>
                        <svg class="icono text-body-secondary" aria-hidden="true"><use href="#i-siguiente"/></svg>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endsection
