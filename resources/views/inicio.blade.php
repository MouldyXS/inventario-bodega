@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <h1 class="h3 mb-3">Inicio</h1>

    @if ($avisoRespaldo)
        <div class="alert alert-danger" role="alert">
            <strong>Copias de seguridad:</strong>
            @include('panel._estado-respaldo', ['respaldo' => $avisoRespaldo])
            <a href="{{ route('configuracion.edit') }}" class="alert-link">Ver detalle</a>
        </div>
    @endif

    {{-- RF-10: indicadores; ninguno suma unidades de productos distintos. --}}
    @php($tarjetas = [
        ['Productos registrados', $indicadores['productos'], route('productos.index'), ''],
        ['Productos con existencia', $indicadores['productos_con_existencia'], route('inventario.index'), ''],
        ['Lotes activos', $indicadores['lotes_activos'], route('inventario.index'), ''],
        ['Próximos a caducar', $indicadores['proximos'], route('caducidad.index', ['estado' => 'proximo']), $indicadores['proximos'] > 0 ? 'border-warning' : ''],
        ['Lotes vencidos', $indicadores['vencidos'], route('caducidad.index', ['estado' => 'vencido']), $indicadores['vencidos'] > 0 ? 'border-danger' : ''],
        ['Bajo el mínimo', $indicadores['bajo_minimo'], route('inventario.index', ['bajo_minimo' => 1]), $indicadores['bajo_minimo'] > 0 ? 'border-warning' : ''],
    ])
    <div class="row g-3 mb-4">
        @foreach ($tarjetas as [$titulo, $valor, $enlace, $borde])
            <div class="col-6 col-xl-4">
                <a href="{{ $enlace }}" class="card card-body h-100 text-decoration-none {{ $borde }}">
                    <div class="small text-body-secondary">{{ $titulo }}</div>
                    <div class="fs-2 fw-semibold text-body">{{ $valor }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <h2 class="h5">Lotes vencidos</h2>
            @include('panel._lotes', ['lotes' => $vencidos, 'vacio' => 'No hay lotes vencidos con existencias.', 'estado' => 'vencido'])

            <h2 class="h5 mt-4">Próximos a caducar <span class="small text-body-secondary fw-normal">({{ $diasAlerta }} días o menos)</span></h2>
            @include('panel._lotes', ['lotes' => $proximos, 'vacio' => 'No hay lotes próximos a caducar.', 'estado' => 'proximo'])
        </div>

        <div class="col-12 col-xl-6">
            <h2 class="h5">Bajo el mínimo</h2>
            @if ($bajoMinimo->isEmpty())
                <p class="text-body-secondary">Ningún producto está por debajo de su mínimo.</p>
            @else
                <div class="list-group mb-1">
                    @foreach ($bajoMinimo as $producto)
                        <a href="{{ route('inventario.show', $producto) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                            <span>{{ $producto->nombre }}</span>
                            <span class="text-nowrap">
                                <strong>{{ $producto->existencia_disponible }}</strong>
                                <span class="text-body-secondary">de {{ $producto->stock_minimo }} {{ $producto->presentacionBase?->nombre }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                @if ($indicadores['bajo_minimo'] > $bajoMinimo->count())
                    <a href="{{ route('inventario.index', ['bajo_minimo' => 1]) }}" class="small">Ver los {{ $indicadores['bajo_minimo'] }}</a>
                @endif
            @endif

            <h2 class="h5 mt-4">Movimientos recientes</h2>
            @if ($recientes->isEmpty())
                <p class="text-body-secondary">Todavía no hay movimientos.</p>
            @else
                <div class="list-group mb-1">
                    @foreach ($recientes as $movimiento)
                        <a href="{{ route('movimientos.show', $movimiento) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $movimiento->tipo->etiqueta() }} #{{ $movimiento->id }}</strong>
                                <span class="small text-body-secondary text-nowrap">{{ $movimiento->created_at->format('d/m H:i') }} · {{ $movimiento->usuario->name }}</span>
                            </div>
                            <div class="small">
                                {{ $movimiento->detalles->first()?->producto->nombre }}:
                                {{ $movimiento->detalles->map(fn ($d) => ($d->cantidad > 0 ? '+' : '').$d->cantidad.' '.$d->presentacion->nombre)->join(', ') }}
                            </div>
                            @if ($movimiento->created_at->format('Y-m-d H:i') !== $movimiento->fecha->format('Y-m-d H:i'))
                                <div class="small text-body-secondary">Ocurrió el {{ $movimiento->fecha->format('d/m/Y H:i') }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('historial.index') }}" class="small">Ver el historial</a>
            @endif
        </div>
    </div>
@endsection
