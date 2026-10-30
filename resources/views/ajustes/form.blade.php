@extends('layouts.app')

@section('titulo', 'Ajuste por conteo')

@section('contenido')
    <h1 class="h3 mb-1">Ajuste por conteo</h1>
    <p class="text-body-secondary">
        {{ $producto->nombre }} · {{ $producto->marca ?: 'Sin marca' }}
        · <a href="{{ route('ajustes.create', ['producto' => $producto->id, 'agotados' => $incluirAgotados ? null : 1]) }}">
            {{ $incluirAgotados ? 'Ocultar lotes agotados' : 'Mostrar lotes agotados' }}
        </a>
    </p>
    <p class="small text-body-secondary">
        Escribe la cantidad contada solo en las filas que revisaste. El sistema registra la diferencia con lo que
        tiene anotado en el momento de guardar. La mercancía encontrada sin lote se registra como recepción.
    </p>

    @foreach (['token', 'conteos'] as $campo)
        @if ($errors->has($campo))
            <div class="alert alert-danger">{{ $errors->first($campo) }}</div>
        @endif
    @endforeach

    @if ($lotes->isEmpty())
        <div class="alert alert-warning">No hay lotes que mostrar.</div>
    @else
        <form method="POST" action="{{ route('ajustes.store', $producto) }}" id="form-ajuste" data-enter-avanza>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="table-responsive mb-3">
                <table class="table align-middle bg-white">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Presentación</th>
                            <th class="text-end">Registrado</th>
                            <th style="width: 10rem">Contado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($fila = 0)
                        @foreach ($lotes as $lote)
                            @php($existentes = $porLote->get($lote->id, collect())->pluck('cantidad', 'presentacion_id'))
                            @foreach ($presentaciones->filter(fn ($p) => $p->activo || ($existentes[$p->id] ?? 0) !== 0) as $presentacion)
                                <tr>
                                    <td>
                                        {{ $lote->etiqueta() }}
                                        <div class="small text-body-secondary">{{ $lote->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}</div>
                                    </td>
                                    <td>{{ $presentacion->nombre }}</td>
                                    <td class="text-end">{{ $existentes[$presentacion->id] ?? 0 }}</td>
                                    <td>
                                        <input type="hidden" name="conteos[{{ $fila }}][lote_id]" value="{{ $lote->id }}">
                                        <input type="hidden" name="conteos[{{ $fila }}][presentacion_id]" value="{{ $presentacion->id }}">
                                        <input type="number" name="conteos[{{ $fila }}][contado]" min="0" step="1" inputmode="numeric"
                                               aria-label="Contado de {{ $presentacion->nombre }} en el lote {{ $lote->etiqueta() }}"
                                               value="{{ old("conteos.$fila.contado") }}" class="form-control">
                                    </td>
                                </tr>
                                @php($fila++)
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mb-3" style="max-width: 48rem">
                <div class="col-12 col-md-6">
                    <label for="motivo" class="form-label">Motivo</label>
                    <select id="motivo" name="motivo" class="form-select @error('motivo') is-invalid @enderror">
                        @foreach ($motivos as $motivo)
                            <option value="{{ $motivo->value }}" @selected(old('motivo', 'conteo_fisico') === $motivo->value)>{{ $motivo->etiqueta() }}</option>
                        @endforeach
                    </select>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="fecha" class="form-label">Fecha y hora del conteo</label>
                    <input type="datetime-local" id="fecha" name="fecha" max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}"
                           class="form-control @error('fecha') is-invalid @enderror" required>
                    @error('fecha')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label for="observacion" class="form-label">Observación <span class="text-body-secondary">(obligatoria)</span></label>
                    <textarea id="observacion" name="observacion" rows="2" maxlength="2000" required
                              class="form-control @error('observacion') is-invalid @enderror">{{ old('observacion') }}</textarea>
                    @error('observacion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @foreach ($errors->getMessages() as $campo => $mensajes)
                @if (str_starts_with($campo, 'conteos.'))
                    <div class="alert alert-danger py-2">{{ $mensajes[0] }}</div>
                @endif
            @endforeach

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg" id="guardar">Registrar ajuste</button>
                <a href="{{ route('inventario.show', $producto) }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.getElementById('form-ajuste')?.addEventListener('submit', () => {
            document.getElementById('guardar').disabled = true;
        });
    </script>
@endpush
