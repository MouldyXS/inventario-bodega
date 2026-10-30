@extends('layouts.app')

@section('titulo', $movimiento->tipo->etiqueta().' #'.$movimiento->id)

@section('contenido')
    <h1 class="h3 mb-3">{{ $movimiento->tipo->etiqueta() }} #{{ $movimiento->id }}</h1>

    <dl class="row bg-white border rounded p-3 mx-0 mb-4">
        <dt class="col-sm-3">Ocurrió</dt>
        <dd class="col-sm-9">{{ $movimiento->fecha->format('d/m/Y H:i') }}</dd>
        @if ($movimiento->created_at->format('Y-m-d H:i') !== $movimiento->fecha->format('Y-m-d H:i'))
            <dt class="col-sm-3">Registrado</dt>
            <dd class="col-sm-9">{{ $movimiento->created_at->format('d/m/Y H:i') }}</dd>
        @endif
        @if ($movimiento->motivo)
            <dt class="col-sm-3">Motivo</dt>
            <dd class="col-sm-9">{{ $movimiento->motivo->etiqueta() }}</dd>
        @endif
        <dt class="col-sm-3">Usuario</dt>
        <dd class="col-sm-9">{{ $movimiento->usuario->name }}</dd>
        @if ($movimiento->observacion)
            <dt class="col-sm-3">Observación</dt>
            <dd class="col-sm-9">{{ $movimiento->observacion }}</dd>
        @endif
        @if ($movimiento->revierte)
            <dt class="col-sm-3">Revierte a</dt>
            <dd class="col-sm-9">
                <a href="{{ route('movimientos.show', $movimiento->revierte) }}">{{ $movimiento->revierte->tipo->etiqueta() }} #{{ $movimiento->revierte->id }}</a>
            </dd>
        @endif
        @if ($movimiento->reversion)
            <dt class="col-sm-3">Revertido por</dt>
            <dd class="col-sm-9">
                <a href="{{ route('movimientos.show', $movimiento->reversion) }}">Reversión #{{ $movimiento->reversion->id }}</a>
            </dd>
        @endif
    </dl>

    @if ($errors->has('movimiento') || $errors->has('token'))
        <div class="alert alert-danger">{{ $errors->first('movimiento') ?: $errors->first('token') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table align-middle bg-white">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Lote</th>
                    <th>Caducidad</th>
                    <th class="text-end">Cantidad</th>
                    <th>Presentación</th>
                    <th class="text-end">Unidades base</th>
                    <th class="text-end">Costo unitario</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movimiento->detalles as $detalle)
                    <tr>
                        <td><a href="{{ route('inventario.show', $detalle->producto_id) }}">{{ $detalle->producto->nombre }}</a></td>
                        <td>{{ $detalle->lote->etiqueta() }}</td>
                        <td>{{ $detalle->lote->fecha_caducidad?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end">{{ $detalle->cantidad > 0 ? '+' : '' }}{{ $detalle->cantidad }}</td>
                        <td>{{ $detalle->presentacion->nombre }}</td>
                        <td class="text-end">{{ $detalle->cantidad_base }}</td>
                        <td class="text-end">{{ $detalle->costo_unitario !== null ? '$'.number_format((float) $detalle->costo_unitario, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        @if ($movimiento->tipo === \App\Enums\TipoMovimiento::Entrada)
            <a href="{{ route('recepciones.create') }}" class="btn btn-primary">Registrar otra recepción</a>
        @elseif ($movimiento->tipo === \App\Enums\TipoMovimiento::Salida)
            <a href="{{ route('salidas.create') }}" class="btn btn-primary">Registrar otra salida</a>
        @endif
        <a href="{{ route('inventario.show', $movimiento->detalles->first()->producto_id) }}" class="btn btn-outline-secondary">Ver existencias del producto</a>
        <a href="{{ route('historial.index') }}" class="btn btn-outline-secondary">Historial</a>
    </div>

    @if ($revertible)
        @can('revertir')
            <details class="card border-danger-subtle" @if ($errors->has('observacion')) open @endif>
                <summary class="card-header bg-white text-danger">Revertir este movimiento</summary>
                <form method="POST" action="{{ route('reversiones.store', $movimiento) }}" class="card-body"
                      onsubmit="return confirm('¿Revertir el movimiento #{{ $movimiento->id }}? Se registrará un movimiento nuevo con las cantidades contrarias.')">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <p class="small text-body-secondary">
                        Se registra un movimiento nuevo con las cantidades contrarias. El original no se modifica.
                        Un movimiento solo puede revertirse una vez.
                    </p>
                    <div class="mb-3">
                        <label for="observacion" class="form-label">Motivo de la reversión <span class="text-body-secondary">(obligatorio)</span></label>
                        <textarea id="observacion" name="observacion" rows="2" maxlength="2000" required
                                  class="form-control @error('observacion') is-invalid @enderror">{{ old('observacion') }}</textarea>
                        @error('observacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-outline-danger">Revertir</button>
                </form>
            </details>
        @endcan
    @endif
@endsection
