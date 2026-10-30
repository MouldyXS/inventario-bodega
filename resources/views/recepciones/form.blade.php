@extends('layouts.app')

@section('titulo', 'Nueva recepción')

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 mb-1">Nueva recepción</h1>
        <div class="text-body-secondary">
            <strong>{{ $producto->nombre }}</strong> · {{ $producto->marca ?: 'Sin marca' }}
            · <a href="{{ route('recepciones.create') }}">Cambiar producto</a>
        </div>
    </div>

    @if ($errors->has('token'))
        <div class="alert alert-danger">{{ $errors->first('token') }}</div>
    @endif
    @if ($errors->has('lineas'))
        <div class="alert alert-danger">{{ $errors->first('lineas') }}</div>
    @endif

    <form method="POST" action="{{ route('recepciones.store') }}" id="form-recepcion" data-enter-avanza>
        @csrf
        <input type="hidden" name="producto_id" value="{{ $producto->id }}">
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <label for="fecha" class="form-label">Fecha y hora de recepción</label>
                <input type="datetime-local" id="fecha" name="fecha" max="{{ now()->format('Y-m-d\TH:i') }}"
                       value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}"
                       class="form-control @error('fecha') is-invalid @enderror" required>
                @error('fecha')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="motivo" class="form-label">Motivo</label>
                <select id="motivo" name="motivo" class="form-select @error('motivo') is-invalid @enderror">
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo->value }}" @selected(old('motivo', 'compra') === $motivo->value)>{{ $motivo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('motivo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <h2 class="h6">Mercancía recibida</h2>
        <p class="small text-body-secondary mb-2">
            Una línea por presentación y lote. El costo es por unidad de la presentación (por ejemplo, el precio de una caja).
        </p>

        <div id="lineas">
            @foreach (old('lineas', [['presentacion_id' => $presentacionInicial]]) as $i => $linea)
                @include('recepciones._linea', ['i' => $i, 'linea' => $linea])
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
            <button type="submit" class="btn btn-primary btn-lg" id="guardar">Registrar recepción</button>
            <a href="{{ route('recepciones.create') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </form>

    <template id="plantilla-linea">
        @include('recepciones._linea', ['i' => '__i__', 'linea' => []])
    </template>
@endsection

@push('scripts')
    <script>
        (() => {
            const lineas = document.getElementById('lineas');
            const plantilla = document.getElementById('plantilla-linea');
            let siguiente = {{ count(old('lineas', [[]])) }} + 1000;

            document.getElementById('anadir-linea').addEventListener('click', () => {
                lineas.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__i__', siguiente++));
            });

            lineas.addEventListener('click', (evento) => {
                if (evento.target.matches('[data-quitar-linea]') && lineas.querySelectorAll('[data-linea]').length > 1) {
                    evento.target.closest('[data-linea]').remove();
                }
            });

            // Ayuda de interfaz contra el doble toque; la garantía es el token (§5.6).
            document.getElementById('form-recepcion').addEventListener('submit', () => {
                const boton = document.getElementById('guardar');
                boton.disabled = true;
                boton.textContent = 'Registrando…';
            });
        })();
    </script>
@endpush
