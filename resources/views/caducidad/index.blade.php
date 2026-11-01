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

    @php
        $filtrosActivos = $filtro !== 'alertas' || $categoriaId;
        $hayFiltros = $busqueda !== '' || $filtrosActivos;
    @endphp
    <form method="GET" action="{{ route('caducidad.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-lg-4">
            <label for="q" class="visually-hidden">Producto</label>
            <input type="search" id="q" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Producto, marca o código">
        </div>
        <div class="col-12 d-md-none">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#filtros-extra"
                    aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}" aria-controls="filtros-extra">Filtros</button>
        </div>
        <div class="filtros-extra row g-2 collapse @if ($filtrosActivos) show @endif" id="filtros-extra">
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
        </div>
        <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filtrar</button>
        </div>
    </form>

    @if ($lotes->isEmpty())
        @include('compartido.vacio', ['mensaje' => 'No hay lotes con existencias en este estado.', 'quitar' => $hayFiltros ? route('caducidad.index') : null])
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Lotes con existencias por caducidad</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Producto</th>
                        <th scope="col" role="columnheader">Lote</th>
                        <th scope="col" role="columnheader">Caducidad</th>
                        <th scope="col" role="columnheader">Existencias</th>
                        <th scope="col" role="columnheader"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($lotes as $lote)
                        @php($estado = $existencias->estado($lote, $diasAlerta))
                        <tr role="row" @class(['table-danger' => $estado === \App\Enums\EstadoCaducidad::Vencido, 'table-warning' => $estado === \App\Enums\EstadoCaducidad::Proximo])>
                            <td role="cell" data-etiqueta="">
                                <div class="d-flex align-items-center gap-3">
                                    @include('compartido.miniatura', ['producto' => $lote->producto, 'lado' => 44])
                                    <a href="{{ route('inventario.show', $lote->producto_id) }}">{{ $lote->producto->nombre }}</a>
                                </div>
                            </td>
                            <td role="cell" data-etiqueta="Lote">{{ $lote->etiqueta() }}</td>
                            <td role="cell" data-etiqueta="Caducidad">
                                {{ $lote->fecha_caducidad->format('d/m/Y') }}
                                <div class="small">
                                    @include('compartido.estado', ['tipo' => $estado->value, 'texto' => $estado->etiqueta()])
                                    {{ \App\Enums\EstadoCaducidad::textoDias($lote->dias_restantes) }}
                                </div>
                            </td>
                            <td role="cell" data-etiqueta="Existencias">
                                @foreach ($porLote->get($lote->id, collect()) as $fila)
                                    <span class="badge bg-body-secondary text-body border fs-6 fw-normal me-1">{{ $fila->cantidad }} {{ $presentaciones[$fila->presentacion_id]->nombre }}</span>
                                @endforeach
                                <div class="small text-body-secondary">Total: {{ $lote->existencia_base }} {{ $presentaciones->first(fn ($p) => $p->producto_id === $lote->producto_id && $p->es_base)?->nombre }}</div>
                            </td>
                            <td role="cell" data-etiqueta="" class="text-end">
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
