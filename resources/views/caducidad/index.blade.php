@extends('layouts.app')

@section('titulo', 'Caducidad')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Caducidad</h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('caducidad.index', ['estado' => 'vencido']) }}" class="btn btn-outline-danger">
                Vencidos <span class="badge text-bg-danger">{{ $resumen['vencidos'] }}</span>
            </a>
            <a href="{{ route('caducidad.index', ['estado' => 'proximo']) }}" class="btn btn-outline-warning text-body">
                Próximos a caducar <span class="badge text-bg-warning">{{ $resumen['proximos'] }}</span>
            </a>
        </div>
    </div>
    <p class="small text-body-secondary">
        Lotes con existencias. «Próximo a caducar» significa que faltan {{ $diasAlerta }} días o menos.
        @can('administrar')
            <a href="{{ route('configuracion.edit') }}">Cambiar el periodo</a>
        @endcan
    </p>

    <form method="GET" action="{{ route('caducidad.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-lg-4">
            <label for="q" class="visually-hidden">Producto</label>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Producto, marca o código">
        </div>
        <div class="col-6 col-lg-3">
            <label for="estado" class="visually-hidden">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="alertas" @selected($filtro === 'alertas')>Vencidos y próximos</option>
                <option value="vencido" @selected($filtro === 'vencido')>Vencidos</option>
                <option value="proximo" @selected($filtro === 'proximo')>Próximos a caducar</option>
                <option value="vigente" @selected($filtro === 'vigente')>Vigentes</option>
                <option value="todos" @selected($filtro === 'todos')>Todos</option>
            </select>
        </div>
        <div class="col-6 col-lg-3">
            <label for="categoria" class="visually-hidden">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($categoriaId === $categoria->id)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filtrar</button>
        </div>
    </form>

    @if ($lotes->isEmpty())
        <p class="text-body-secondary">No hay lotes con existencias en este estado.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Lote</th>
                        <th>Caducidad</th>
                        <th>Existencias</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lotes as $lote)
                        @php($estado = $existencias->estado($lote, $diasAlerta))
                        <tr @class(['table-danger' => $estado === \App\Enums\EstadoCaducidad::Vencido, 'table-warning' => $estado === \App\Enums\EstadoCaducidad::Proximo])>
                            <td><a href="{{ route('inventario.show', $lote->producto_id) }}">{{ $lote->producto->nombre }}</a></td>
                            <td>{{ $lote->etiqueta() }}</td>
                            <td>
                                {{ $lote->fecha_caducidad->format('d/m/Y') }}
                                <div class="small">
                                    <span class="badge {{ $estado->claseInsignia() }}">{{ $estado->etiqueta() }}</span>
                                    {{ \App\Enums\EstadoCaducidad::textoDias($lote->dias_restantes) }}
                                </div>
                            </td>
                            <td>
                                @foreach ($porLote->get($lote->id, collect()) as $fila)
                                    <span class="badge text-bg-light border fs-6 fw-normal me-1">{{ $fila->cantidad }} {{ $presentaciones[$fila->presentacion_id]->nombre }}</span>
                                @endforeach
                                <div class="small text-body-secondary">Total: {{ $lote->existencia_base }} {{ $presentaciones->first(fn ($p) => $p->producto_id === $lote->producto_id && $p->es_base)?->nombre }}</div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('salidas.create', ['producto' => $lote->producto_id, 'motivo' => $estado === \App\Enums\EstadoCaducidad::Vencido ? 'vencido' : null]) }}"
                                   class="btn btn-outline-secondary btn-sm text-nowrap">Registrar salida</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $lotes->links() }}
    @endif
@endsection
