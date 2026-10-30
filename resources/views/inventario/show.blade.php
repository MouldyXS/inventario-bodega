@extends('layouts.app')

@section('titulo', 'Existencias de '.$producto->nombre)

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $producto->nombre }}</h1>
            <div class="text-body-secondary">
                {{ $producto->marca ?: 'Sin marca' }} · {{ $producto->categoria->nombre }}
                · <a href="{{ route('productos.show', $producto) }}">Ficha del producto</a>
            </div>
        </div>
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
            <div class="card card-body">
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
            <table class="table align-middle bg-white">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Caducidad</th>
                        <th>Existencias por presentación</th>
                        <th class="text-end">Total ({{ $base?->nombre }})</th>
                        <th>Recepciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lotes as $lote)
                        @php($estado = $existencias->estado($lote, $diasAlerta))
                        <tr @class(['table-danger' => $estado === \App\Enums\EstadoCaducidad::Vencido, 'table-warning' => $estado === \App\Enums\EstadoCaducidad::Proximo])>
                            <td>{{ $lote->etiqueta() }}</td>
                            <td>
                                {{ $lote->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}
                                @if ($estado !== null)
                                    <div class="small">
                                        <span class="badge {{ $estado->claseInsignia() }}">{{ $estado->etiqueta() }}</span>
                                        {{ \App\Enums\EstadoCaducidad::textoDias($existencias->diasRestantes($lote)) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @forelse ($porLote->get($lote->id, collect())->sortByDesc(fn ($f) => $presentaciones[$f->presentacion_id]->factor) as $fila)
                                    <span class="badge text-bg-light border fs-6 fw-normal me-1">
                                        {{ $fila->cantidad }} {{ $presentaciones[$fila->presentacion_id]->nombre }}
                                    </span>
                                @empty
                                    <span class="text-body-secondary">Agotado</span>
                                @endforelse
                            </td>
                            <td class="text-end fw-semibold">{{ $lote->existencia_base }}</td>
                            <td class="small text-body-secondary">
                                {{ $lote->primera_recepcion?->format('d/m/Y') }}
                                @if ($lote->ultima_recepcion && $lote->ultima_recepcion->ne($lote->primera_recepcion))
                                    – {{ $lote->ultima_recepcion->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
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
