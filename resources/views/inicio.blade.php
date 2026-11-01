@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 mb-0">Inicio</h1>
        <div class="text-body-secondary">Qué necesita atención hoy y accesos rápidos.</div>
    </div>

    {{-- Lo urgente primero: solo aparecen los avisos que tienen algo que atender. --}}
    @php
        $atencion = [
            ['peligro', 'vencido', 'Lotes vencidos', 'Retíralos o ajusta su existencia', $indicadores['vencidos'], route('caducidad.index', ['estado' => 'vencido'])],
            ['aviso', 'reloj', 'Próximos a caducar', "Vencen en {$diasAlerta} días o menos", $indicadores['proximos'], route('caducidad.index', ['estado' => 'proximo'])],
            ['aviso', 'bajo', 'Bajo el mínimo', 'Productos por debajo de su existencia mínima', $indicadores['bajo_minimo'], route('inventario.index', ['bajo_minimo' => 1])],
        ];
        $atencion = array_values(array_filter($atencion, fn ($fila) => $fila[4] > 0));
    @endphp
    <section class="card mb-4" aria-labelledby="atencion-titulo">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0" id="atencion-titulo">Requiere atención</h2>
        </div>
        @if ($atencion === [] && ! $avisoRespaldo)
            <div class="card-body d-flex align-items-center gap-3">
                <span class="fila-atencion__icono fila-atencion--exito"><svg class="icono" aria-hidden="true"><use href="#i-exito"/></svg></span>
                <div>
                    <strong>Todo en orden</strong>
                    <div class="small text-body-secondary">No hay lotes vencidos ni próximos a caducar, ningún producto está bajo su mínimo y las copias están al día.</div>
                </div>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($atencion as [$tono, $icono, $titulo, $detalle, $cifra, $enlace])
                    <a href="{{ $enlace }}" class="list-group-item list-group-item-action fila-atencion">
                        <span class="fila-atencion__icono fila-atencion--{{ $tono }}"><svg class="icono" aria-hidden="true"><use href="#i-{{ $icono }}"/></svg></span>
                        <span class="fila-atencion__texto">
                            <strong>{{ $titulo }}</strong>
                            <small class="text-body-secondary">{{ $detalle }}</small>
                        </span>
                        <span class="fila-atencion__cifra">{{ $cifra }}</span>
                        <svg class="icono text-body-secondary" aria-hidden="true"><use href="#i-siguiente"/></svg>
                    </a>
                @endforeach
                @if ($avisoRespaldo)
                    <a href="{{ route('configuracion.edit') }}" class="list-group-item list-group-item-action fila-atencion">
                        <span class="fila-atencion__icono fila-atencion--peligro"><svg class="icono" aria-hidden="true"><use href="#i-alerta"/></svg></span>
                        <span class="fila-atencion__texto">
                            <strong>Copias de seguridad:</strong>
                            @include('panel._estado-respaldo', ['respaldo' => $avisoRespaldo])
                        </span>
                        <svg class="icono text-body-secondary" aria-hidden="true"><use href="#i-siguiente"/></svg>
                    </a>
                @endif
            </div>
        @endif
    </section>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('recepciones.create') }}" class="btn btn-primary w-100 acceso-grande">Recibir mercancía<small>Entrada</small></a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('salidas.create') }}" class="btn btn-success w-100 acceso-grande">Retirar mercancía<small>Salida</small></a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('inventario.index') }}" class="btn btn-outline-primary w-100 acceso-grande">Consultar inventario<small>Buscar un producto</small></a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('caducidad.index') }}" class="btn btn-outline-secondary w-100 acceso-grande">Caducidad<small>Vencidos y próximos</small></a>
        </div>
    </div>

    {{-- RF-10: indicadores; ninguno suma unidades de productos distintos. --}}
    @php($tarjetas = [
        ['Productos registrados', $indicadores['productos'], route('productos.index')],
        ['Productos con existencia', $indicadores['productos_con_existencia'], route('inventario.index')],
        ['Lotes activos', $indicadores['lotes_activos'], route('inventario.index')],
    ])
    <div class="row g-3 mb-4">
        @foreach ($tarjetas as [$titulo, $valor, $enlace])
            <div class="col-4">
                <a href="{{ $enlace }}" class="card card-body h-100 text-decoration-none indicador">
                    <div class="small text-body-secondary">{{ $titulo }}</div>
                    <div class="fs-3 fw-semibold text-body">{{ $valor }}</div>
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
