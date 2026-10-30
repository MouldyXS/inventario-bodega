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
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-end">Productos</th>
                        @can('gestionar-catalogo')
                            <th></th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categorias as $categoria)
                        <tr>
                            <td>
                                <a href="{{ route('productos.index', ['categoria' => $categoria->id, 'estado' => 'todos']) }}">{{ $categoria->nombre }}</a>
                            </td>
                            <td class="text-body-secondary">{{ $categoria->descripcion }}</td>
                            <td class="text-end">{{ $categoria->productos_count }}</td>
                            @can('gestionar-catalogo')
                                <td class="text-end">
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
