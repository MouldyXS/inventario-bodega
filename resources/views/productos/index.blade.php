@extends('layouts.app')

@section('titulo', 'Productos')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Productos</h1>
        @can('gestionar-catalogo')
            <a href="{{ route('productos.create') }}" class="btn btn-primary">Nuevo producto</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('productos.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <label for="q" class="visually-hidden">Buscar</label>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control"
                   placeholder="Nombre, marca o código de barras">
        </div>
        <div class="col-6 col-md-3">
            <label for="categoria" class="visually-hidden">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($categoriaId === $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="estado" class="visually-hidden">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="activos" @selected($estado === 'activos')>Activos</option>
                <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                <option value="todos" @selected($estado === 'todos')>Todos</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
        </div>
    </form>

    @if ($productos->isEmpty())
        <p class="text-body-secondary">No hay productos que coincidan.</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Marca</th>
                        <th>Categoría</th>
                        <th>Unidad base</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productos as $producto)
                        <tr>
                            <td><a href="{{ route('productos.show', $producto) }}">{{ $producto->nombre }}</a></td>
                            <td>{{ $producto->marca }}</td>
                            <td>{{ $producto->categoria->nombre }}</td>
                            <td>{{ $producto->presentacionBase?->nombre }}</td>
                            <td>
                                @if ($producto->activo)
                                    <span class="badge text-bg-success">Activo</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $productos->links() }}
    @endif
@endsection
