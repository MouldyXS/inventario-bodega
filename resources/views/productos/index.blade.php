@extends('layouts.app')

@section('titulo', 'Productos')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Productos</h1>
        @can('gestionar-catalogo')
            <a href="{{ route('productos.create') }}" class="btn btn-primary">Nuevo producto</a>
        @endcan
    </div>

    @php
        $filtrosActivos = $categoriaId || $estado !== 'activos';
        $hayFiltros = $busqueda !== '' || $filtrosActivos;
    @endphp
    <form method="GET" action="{{ route('productos.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <label for="q" class="visually-hidden">Buscar</label>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control"
                   placeholder="Nombre, marca o código de barras">
        </div>
        <div class="col-12 d-md-none">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#filtros-extra"
                    aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}" aria-controls="filtros-extra">Filtros</button>
        </div>
        <div class="filtros-extra row g-2 collapse @if ($filtrosActivos) show @endif" id="filtros-extra">
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
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
        </div>
    </form>

    @if ($productos->isEmpty())
        @include('compartido.vacio', ['mensaje' => 'No hay productos que coincidan.', 'quitar' => $hayFiltros ? route('productos.index') : null])
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Productos</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Producto</th>
                        <th scope="col" role="columnheader">Marca</th>
                        <th scope="col" role="columnheader">Categoría</th>
                        <th scope="col" role="columnheader">Unidad base</th>
                        <th scope="col" role="columnheader">Estado</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($productos as $producto)
                        <tr role="row">
                            <td role="cell" data-etiqueta="">
                                <div class="d-flex align-items-center gap-3">
                                    @include('compartido.miniatura', ['producto' => $producto, 'lado' => 44])
                                    <a href="{{ route('productos.show', $producto) }}">{{ $producto->nombre }}</a>
                                </div>
                            </td>
                            <td role="cell" data-etiqueta="Marca">{{ $producto->marca }}</td>
                            <td role="cell" data-etiqueta="Categoría">{{ $producto->categoria->nombre }}</td>
                            <td role="cell" data-etiqueta="Unidad base">{{ $producto->presentacionBase?->nombre }}</td>
                            <td role="cell" data-etiqueta="Estado">
                                @if ($producto->activo)
                                    @include('compartido.estado', ['tipo' => 'activo'])
                                @else
                                    @include('compartido.estado', ['tipo' => 'inactivo'])
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
