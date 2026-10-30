@extends('layouts.app')

@section('titulo', 'Nueva salida')

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 mb-1">Nueva salida</h1>
        <div class="text-body-secondary">
            <strong>{{ $producto->nombre }}</strong> · {{ $producto->marca ?: 'Sin marca' }}
            · <a href="{{ route('salidas.create') }}">Cambiar producto</a>
        </div>
    </div>

    @if ($lotes->isEmpty())
        <div class="alert alert-warning">Este producto no tiene existencias.</div>
    @else
        {{-- RF-07: lotes en orden FEFO; el recomendado se señala, no se elige solo. --}}
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle bg-white mb-0">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Caducidad</th>
                        <th>Existencias</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lotes as $lote)
                        <tr @class(['table-danger' => $existencias->estaVencido($lote), 'table-success' => $lote->id === $recomendadoId])>
                            <td>
                                {{ $lote->etiqueta() }}
                                @if ($lote->id === $recomendadoId)
                                    <span class="badge text-bg-success">Recomendado</span>
                                @endif
                            </td>
                            <td>
                                {{ $lote->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}
                                @if ($existencias->estaVencido($lote))
                                    <span class="badge text-bg-danger">Vencido</span>
                                @endif
                            </td>
                            <td>{{ $porLote->get($lote->id, collect())->map(fn ($f) => $f->cantidad.' '.$presentaciones[$f->presentacion_id]->nombre)->join(', ') }}</td>
                            <td class="text-end">
                                <a href="{{ route('conversiones.create', ['lote' => $lote, 'volver' => 'salida']) }}"
                                   class="btn btn-outline-secondary btn-sm text-nowrap">Abrir / convertir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-body-secondary">
            Si no hay suficientes unidades sueltas, abre primero una caja del lote: volverás aquí para registrar la salida.
        </p>

        @if ($errors->has('token') || $errors->has('lineas'))
            <div class="alert alert-danger">{{ $errors->first('token') ?: $errors->first('lineas') }}</div>
        @endif

        <form method="POST" action="{{ route('salidas.store') }}" id="form-salida" data-enter-avanza>
            @csrf
            <input type="hidden" name="producto_id" value="{{ $producto->id }}">
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label for="motivo" class="form-label">Motivo</label>
                    <select id="motivo" name="motivo" class="form-select @error('motivo') is-invalid @enderror" required>
                        @foreach ($motivos as $motivo)
                            <option value="{{ $motivo->value }}" @selected(old('motivo', $motivoInicial->value) === $motivo->value)>{{ $motivo->etiqueta() }}</option>
                        @endforeach
                    </select>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="fecha" class="form-label">Fecha y hora</label>
                    <input type="datetime-local" id="fecha" name="fecha" max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}"
                           class="form-control @error('fecha') is-invalid @enderror" required>
                    @error('fecha')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div id="lineas">
                @foreach (old('lineas', [[]]) as $i => $linea)
                    @include('salidas._linea', ['i' => $i, 'linea' => $linea])
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="anadir-linea">+ Añadir línea</button>

            <div class="mb-3" style="max-width: 48rem">
                <label for="observacion" class="form-label">Observaciones <span class="text-body-secondary">(obligatorias con "Otro motivo")</span></label>
                <textarea id="observacion" name="observacion" rows="2" maxlength="2000"
                          class="form-control @error('observacion') is-invalid @enderror">{{ old('observacion') }}</textarea>
                @error('observacion')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg" id="guardar">Registrar salida</button>
                <a href="{{ route('salidas.create') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            </div>
        </form>

        <template id="plantilla-linea">
            @include('salidas._linea', ['i' => '__i__', 'linea' => []])
        </template>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const formulario = document.getElementById('form-salida');
            if (!formulario) return;

            const lineas = document.getElementById('lineas');
            const plantilla = document.getElementById('plantilla-linea');
            const motivo = document.getElementById('motivo');
            let siguiente = {{ count(old('lineas', [[]])) }} + 1000;

            // RF-07: los lotes vencidos solo se pueden elegir con motivo vencido o dañado.
            // Es una ayuda de interfaz; el servidor lo comprueba siempre.
            const actualizarVencidos = () => {
                const permitido = ['vencido', 'danado'].includes(motivo.value);
                lineas.querySelectorAll('option[data-vencido]').forEach((opcion) => {
                    opcion.disabled = !permitido;
                    if (!permitido && opcion.selected) opcion.parentElement.value = '';
                });
            };

            motivo.addEventListener('change', actualizarVencidos);

            document.getElementById('anadir-linea').addEventListener('click', () => {
                lineas.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__i__', siguiente++));
                actualizarVencidos();
            });

            lineas.addEventListener('click', (evento) => {
                if (evento.target.matches('[data-quitar-linea]') && lineas.querySelectorAll('[data-linea]').length > 1) {
                    evento.target.closest('[data-linea]').remove();
                }
            });

            formulario.addEventListener('submit', () => {
                const boton = document.getElementById('guardar');
                boton.disabled = true;
                boton.textContent = 'Registrando…';
            });

            actualizarVencidos();
        })();
    </script>
@endpush
