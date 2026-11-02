@extends('layouts.app')

@section('titulo', $titulo)

{{--
    Paso 1 de Recibir y Retirar: lista de productos que se filtra al escribir (resources/js/app.js,
    [data-selector-producto]). Sin JavaScript queda el formulario GET de siempre: el servidor ya deja
    ocultos los que no coinciden con ?q=. «data-busqueda» lleva nombre, marca y categoría sin tildes
    ni mayúsculas, igual que la colación del servidor.
--}}
@section('contenido')
    @php
        $mensajeVacio = 'No hay productos activos que coincidan con «'.$busqueda.'».';
        $ocultos = $coincidentes === null ? [] : array_flip($coincidentes);
        $visibles = $coincidentes === null ? $productos->count() : count($coincidentes);
    @endphp

    <h1 class="h3 mb-3">{{ $titulo }}</h1>

    <div class="selector-producto" @if ($listaCompleta) data-selector-producto @endif>
        <form method="GET" action="{{ route($ruta) }}" role="search" class="selector-producto__busqueda row g-2">
            <div class="col-12 col-md-9">
                <label for="q" class="form-label">Producto</label>
                <div class="campo-busqueda">
                    <svg class="icono" aria-hidden="true"><use href="#i-buscar"/></svg>
                    <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control form-control-lg"
                           placeholder="Nombre, marca o código" autofocus autocomplete="off"
                           inputmode="search" enterkeyhint="search" aria-controls="lista-productos">
                </div>
            </div>
            <div class="col-12 col-md-3 d-grid align-items-end" data-buscar>
                <button type="submit" class="btn btn-primary btn-lg mt-md-4">Buscar</button>
            </div>
        </form>

        @if ($listaCompleta)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-2">
                <span class="small text-body-secondary" data-contador aria-hidden="true">{{ $visibles }} {{ $visibles === 1 ? 'producto' : 'productos' }}</span>
                @if ($conExistencias)
                    <div class="form-check form-switch mb-0" data-solo-existencias hidden>
                        <input class="form-check-input" type="checkbox" role="switch" id="solo-existencias">
                        <label class="form-check-label" for="solo-existencias">Solo con existencias</label>
                    </div>
                @endif
            </div>
            <p class="visually-hidden" role="status" aria-live="polite" data-anuncio></p>
        @endif

        @if ($listaCompleta || $productos->isNotEmpty())
            <ul class="list-group list-unstyled" id="lista-productos" data-lista>
                @foreach ($productos as $producto)
                    @php
                        $agotado = $conExistencias && $producto->existencia_fisica === 0;
                        $oculto = $coincidentes !== null && ! isset($ocultos[$producto->id]);
                    @endphp
                    <li class="list-group-item p-0 @if ($agotado) producto-agotado @endif" data-producto
                        data-busqueda="{{ Str::lower(Str::ascii($producto->nombre.' '.$producto->marca.' '.$producto->categoria->nombre)) }}"
                        data-nombre="{{ Str::lower(Str::ascii($producto->nombre)) }}"
                        @if ($agotado) data-agotado @endif @if ($oculto) hidden @endif>
                        <a href="{{ route($ruta, ['producto' => $producto->id]) }}" class="resultado-producto text-reset text-decoration-none">
                            @include('compartido.miniatura', ['producto' => $producto, 'lado' => 52])
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block fw-semibold nombre-producto">{{ $producto->nombre }}</span>
                                <span class="d-block small text-body-secondary">{{ $producto->marca ?: 'Sin marca' }} · {{ $producto->categoria->nombre }}</span>
                                @if ($conExistencias)
                                    <span class="d-block small mt-1">
                                        @if ($agotado)
                                            @include('compartido.estado', ['tipo' => 'agotado'])
                                        @else
                                            <span class="text-body-secondary">Existencias:</span> {{ number_format($producto->existencia_fisica, 0, ',', '.') }} {{ $producto->presentacionBase?->nombre }}
                                        @endif
                                    </span>
                                @endif
                            </span>
                            <svg class="icono text-body-secondary" aria-hidden="true"><use href="#i-siguiente"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        @elseif ($busqueda === '')
            <p class="text-body-secondary mt-3">Hay muchos productos para listarlos todos: escribe el nombre o la marca, o escanea el código.</p>
        @endif

        @if ($busqueda !== '' || $listaCompleta)
            <div class="estado-vacio mt-3" role="status" data-vacio @if ($visibles > 0) hidden @endif>
                <svg class="icono" aria-hidden="true"><use href="#i-info"/></svg>
                <p class="mb-0" data-vacio-texto>{{ $visibles > 0 ? '' : $mensajeVacio }}</p>
                <a href="{{ route($ruta) }}" class="btn btn-outline-primary btn-sm mt-3" data-limpiar>Quitar filtro</a>
            </div>
        @endif
    </div>
@endsection
