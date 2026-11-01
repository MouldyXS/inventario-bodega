@extends('layouts.app')

@section('titulo', 'Inventario')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Inventario</h1>
        <a href="{{ route('recepciones.create') }}" class="btn btn-primary">Nueva recepción</a>
    </div>

    @php
        $filtrosActivos = $categoriaId || $estado !== 'activos';
        $hayFiltros = $busqueda !== '' || $filtrosActivos || $bajoMinimo;
    @endphp
    <form method="GET" action="{{ route('inventario.index') }}" class="row g-2 mb-3">
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
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" id="bajo_minimo" name="bajo_minimo" value="1" class="form-check-input" @checked($bajoMinimo)>
                <label for="bajo_minimo" class="form-check-label">Solo productos bajo el mínimo</label>
            </div>
        </div>
    </form>

    @if ($productos->isEmpty())
        @include('compartido.vacio', ['mensaje' => 'No hay productos que coincidan.', 'quitar' => $hayFiltros ? route('inventario.index') : null])
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Inventario de productos</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Producto</th>
                        <th scope="col" role="columnheader">Categoría</th>
                        <th scope="col" role="columnheader" class="text-end">Disponible</th>
                        <th scope="col" role="columnheader" class="text-end">Física</th>
                        <th scope="col" role="columnheader">Unidad</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($productos as $producto)
                        <tr role="row">
                            <td role="cell" data-etiqueta="">
                                <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                                    @include('compartido.miniatura', ['producto' => $producto, 'lado' => 44])
                                    <a href="{{ route('inventario.show', $producto) }}">{{ $producto->nombre }}</a>
                                    @if ($producto->stock_minimo !== null && $producto->existencia_disponible < $producto->stock_minimo)
                                        @include('compartido.estado', ['tipo' => 'bajo'])
                                    @endif
                                </div>
                            </td>
                            <td role="cell" data-etiqueta="Categoría">{{ $producto->categoria->nombre }}</td>
                            <td role="cell" data-etiqueta="Disponible" class="text-end fw-semibold">{{ $producto->existencia_disponible }}</td>
                            <td role="cell" data-etiqueta="Física" class="text-end text-body-secondary">{{ $producto->existencia_fisica }}</td>
                            <td role="cell" data-etiqueta="Unidad">{{ $producto->presentacionBase?->nombre }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-body-secondary">Disponible excluye los lotes vencidos; física incluye todos los lotes.</p>

        {{ $productos->links() }}
    @endif
@endsection
