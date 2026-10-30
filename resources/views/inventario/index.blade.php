@extends('layouts.app')

@section('titulo', 'Inventario')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Inventario</h1>
        <a href="{{ route('recepciones.create') }}" class="btn btn-primary">Nueva recepción</a>
    </div>

    <form method="GET" action="{{ route('inventario.index') }}" class="row g-2 mb-3">
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
        <div class="col-12">
            <div class="form-check">
                <input type="checkbox" id="bajo_minimo" name="bajo_minimo" value="1" class="form-check-input" @checked($bajoMinimo)>
                <label for="bajo_minimo" class="form-check-label">Solo productos bajo el mínimo</label>
            </div>
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
                        <th>Categoría</th>
                        <th class="text-end">Disponible</th>
                        <th class="text-end">Física</th>
                        <th>Unidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productos as $producto)
                        <tr>
                            <td>
                                <a href="{{ route('inventario.show', $producto) }}">{{ $producto->nombre }}</a>
                                @if ($producto->stock_minimo !== null && $producto->existencia_disponible < $producto->stock_minimo)
                                    <span class="badge text-bg-warning ms-1">Bajo mínimo</span>
                                @endif
                            </td>
                            <td>{{ $producto->categoria->nombre }}</td>
                            <td class="text-end fw-semibold">{{ $producto->existencia_disponible }}</td>
                            <td class="text-end text-body-secondary">{{ $producto->existencia_fisica }}</td>
                            <td>{{ $producto->presentacionBase?->nombre }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-body-secondary">Disponible excluye los lotes vencidos; física incluye todos los lotes.</p>

        {{ $productos->links() }}
    @endif
@endsection
