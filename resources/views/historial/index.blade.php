@extends('layouts.app')

@section('titulo', 'Historial')

@section('contenido')
    <h1 class="h3 mb-3">Historial de movimientos</h1>

    @php
        $filtrosActivos = $filtros['tipo'] || $filtros['usuario'] || $filtros['desde'] || $filtros['hasta'];
        $hayFiltros = $filtros['q'] !== '' || $filtrosActivos;
    @endphp
    <form method="GET" action="{{ route('historial.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-lg-4">
            <label for="q" class="visually-hidden">Producto</label>
            <input type="search" id="q" name="q" value="{{ $filtros['q'] }}" class="form-control" placeholder="Producto, marca o código">
        </div>
        <div class="col-12 d-md-none">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#filtros-extra"
                    aria-expanded="{{ $filtrosActivos ? 'true' : 'false' }}" aria-controls="filtros-extra">Filtros</button>
        </div>
        <div class="filtros-extra row g-2 collapse @if ($filtrosActivos) show @endif" id="filtros-extra">
        <div class="col-6 col-lg-2">
            <label for="tipo" class="visually-hidden">Tipo</label>
            <select id="tipo" name="tipo" class="form-select">
                <option value="">Todos los tipos</option>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->value }}" @selected($filtros['tipo'] === $tipo)>{{ $tipo->etiqueta() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="usuario" class="visually-hidden">Usuario</label>
            <select id="usuario" name="usuario" class="form-select">
                <option value="">Todos los usuarios</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected($filtros['usuario'] === $usuario->id)>{{ $usuario->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="desde" class="form-label small mb-0">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
            <label for="hasta" class="form-label small mb-0">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control">
        </div>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary">Filtrar</button>
            <a href="{{ route('historial.index') }}" class="btn btn-link">Quitar filtros</a>
        </div>
    </form>

    @if ($detalles->isEmpty())
        @include('compartido.vacio', ['mensaje' => 'No hay movimientos que coincidan.', 'quitar' => $hayFiltros ? route('historial.index') : null])
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Movimientos</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Ocurrió</th>
                        <th scope="col" role="columnheader">Tipo</th>
                        <th scope="col" role="columnheader">Producto</th>
                        <th scope="col" role="columnheader">Lote</th>
                        <th scope="col" role="columnheader" class="text-end">Cantidad</th>
                        <th scope="col" role="columnheader">Usuario</th>
                        <th scope="col" role="columnheader">Motivo / observación</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($detalles as $detalle)
                        @php($movimiento = $detalle->movimiento)
                        <tr role="row">
                            <td role="cell" data-etiqueta="" class="text-nowrap">
                                <a href="{{ route('movimientos.show', $movimiento) }}">{{ $movimiento->fecha->format('d/m/Y H:i') }}</a>
                                @if ($movimiento->created_at->format('Y-m-d H:i') !== $movimiento->fecha->format('Y-m-d H:i'))
                                    <div class="small text-body-secondary">Registrado {{ $movimiento->created_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td role="cell" data-etiqueta="Tipo">{{ $movimiento->tipo->etiqueta() }} #{{ $movimiento->id }}</td>
                            <td role="cell" data-etiqueta="Producto">{{ $detalle->producto->nombre }}</td>
                            <td role="cell" data-etiqueta="Lote">{{ $detalle->lote->etiqueta() }}</td>
                            <td role="cell" data-etiqueta="Cantidad" @class(['text-end text-nowrap', 'text-success' => $detalle->cantidad > 0, 'text-danger' => $detalle->cantidad < 0])>
                                <span class="fw-semibold">{{ $detalle->cantidad > 0 ? '+' : '' }}{{ $detalle->cantidad }}</span>
                                {{ $detalle->presentacion->nombre }}
                            </td>
                            <td role="cell" data-etiqueta="Usuario">{{ $movimiento->usuario->name }}</td>
                            <td role="cell" data-etiqueta="Motivo / observación" class="small">
                                {{ $movimiento->motivo?->etiqueta() }}
                                @if ($movimiento->observacion)
                                    <div class="text-body-secondary">{{ Str::limit($movimiento->observacion, 80) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $detalles->links() }}
    @endif
@endsection
