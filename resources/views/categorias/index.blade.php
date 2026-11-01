@extends('layouts.app')

@section('titulo', 'Categorías')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Categorías</h1>
        @can('gestionar-catalogo')
            <a href="{{ route('categorias.create') }}" class="btn btn-primary">Nueva categoría</a>
        @endcan
    </div>

    @if ($categorias->isEmpty())
        <p class="text-body-secondary">Todavía no hay categorías.</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Categorías</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Nombre</th>
                        <th scope="col" role="columnheader">Descripción</th>
                        <th scope="col" role="columnheader" class="text-end">Productos</th>
                        @can('gestionar-catalogo')
                            <th scope="col" role="columnheader"><span class="visually-hidden">Acciones</span></th>
                        @endcan
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($categorias as $categoria)
                        <tr role="row">
                            <td role="cell" data-etiqueta="">
                                <a href="{{ route('productos.index', ['categoria' => $categoria->id, 'estado' => 'todos']) }}">{{ $categoria->nombre }}</a>
                            </td>
                            <td role="cell" data-etiqueta="Descripción" class="text-body-secondary">{{ $categoria->descripcion }}</td>
                            <td role="cell" data-etiqueta="Productos" class="text-end">{{ $categoria->productos_count }}</td>
                            @can('gestionar-catalogo')
                                <td role="cell" data-etiqueta="" class="text-end">
                                    <a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
