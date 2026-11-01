@extends('layouts.app')

@section('titulo', 'Existencias de '.$producto->nombre)

@section('contenido')
    <x-cabecera-producto :producto="$producto" :titulo="$producto->nombre">
        {{ $producto->marca ?: 'Sin marca' }} · {{ $producto->categoria->nombre }}
        · <a href="{{ route('productos.show', $producto) }}">Ficha del producto</a>
    </x-cabecera-producto>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <div class="d-flex flex-wrap gap-2">
            @if ($producto->activo)
                <a href="{{ route('recepciones.create', ['producto' => $producto->id]) }}" class="btn btn-primary">Registrar recepción</a>
            @endif
            <a href="{{ route('salidas.create', ['producto' => $producto->id]) }}" class="btn btn-outline-primary">Registrar salida</a>
            @can('ajustar-inventario')
                <a href="{{ route('ajustes.create', $producto) }}" class="btn btn-outline-secondary">Ajuste por conteo</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-body indicador">
                <div class="small text-body-secondary">Disponible</div>
                <div class="fs-4 fw-semibold">{{ $existenciaDisponible }} <span class="fs-6 fw-normal">{{ $base?->nombre }}</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-body">
                <div class="small text-body-secondary">Física (incluye vencidos)</div>
                <div class="fs-4 fw-semibold">{{ $existenciaFisica }} <span class="fs-6 fw-normal">{{ $base?->nombre }}</span></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-2">Lotes <span class="small text-body-secondary fw-normal">(caducidad más próxima primero)</span></h2>
        <a class="small" href="{{ route('inventario.show', ['producto' => $producto->id, 'agotados' => $incluirAgotados ? null : 1]) }}">
            {{ $incluirAgotados ? 'Ocultar agotados' : 'Mostrar agotados' }}
        </a>
    </div>

    @if ($lotes->isEmpty())
        <p class="text-body-secondary">No hay lotes con existencias.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Lotes del producto, con la caducidad más próxima primero</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Lote</th>
                        <th scope="col" role="columnheader">Caducidad</th>
                        <th scope="col" role="columnheader">Existencias por presentación</th>
                        <th scope="col" role="columnheader" class="text-end">Total ({{ $base?->nombre }})</th>
                        <th scope="col" role="columnheader">Recepciones</th>
                        <th scope="col" role="columnheader"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($lotes as $lote)
                        @php($estado = $existencias->estado($lote, $diasAlerta))
                        <tr role="row" @class(['table-danger' => $estado === \App\Enums\EstadoCaducidad::Vencido, 'table-warning' => $estado === \App\Enums\EstadoCaducidad::Proximo])>
                            <td role="cell" data-etiqueta="">{{ $lote->etiqueta() }}</td>
                            <td role="cell" data-etiqueta="Caducidad">
                                {{ $lote->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}
                                @if ($estado !== null)
                                    <div class="small">
                                        @include('compartido.estado', ['tipo' => $estado->value, 'texto' => $estado->etiqueta()])
                                        {{ \App\Enums\EstadoCaducidad::textoDias($existencias->diasRestantes($lote)) }}
                                    </div>
                                @endif
                            </td>
                            <td role="cell" data-etiqueta="Existencias">
                                @forelse ($porLote->get($lote->id, collect())->sortByDesc(fn ($f) => $presentaciones[$f->presentacion_id]->factor) as $fila)
                                    <span class="badge bg-body-secondary text-body border fs-6 fw-normal me-1">
                                        {{ $fila->cantidad }} {{ $presentaciones[$fila->presentacion_id]->nombre }}
                                    </span>
                                @empty
                                    <span class="text-body-secondary">Agotado</span>
                                @endforelse
                            </td>
                            <td role="cell" data-etiqueta="Total ({{ $base?->nombre }})" class="text-end fw-semibold">{{ $lote->existencia_base }}</td>
                            <td role="cell" data-etiqueta="Recepciones" class="small text-body-secondary">
                                {{ $lote->primera_recepcion?->format('d/m/Y') }}
                                @if ($lote->ultima_recepcion && $lote->ultima_recepcion->ne($lote->primera_recepcion))
                                    – {{ $lote->ultima_recepcion->format('d/m/Y') }}
                                @endif
                            </td>
                            <td role="cell" data-etiqueta="" class="text-end text-nowrap">
                                @if ($lote->existencia_base !== 0)
                                    <a href="{{ route('conversiones.create', $lote) }}" class="btn btn-outline-secondary btn-sm">Abrir / convertir</a>
                                @endif
                                @can('ajustar-inventario')
                                    <a href="{{ route('lotes.edit', $lote) }}" class="btn btn-outline-secondary btn-sm">Corregir</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
