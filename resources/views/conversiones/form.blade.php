@extends('layouts.app')

@section('titulo', 'Abrir o convertir')

@section('contenido')
    <x-cabecera-producto :producto="$producto" titulo="Abrir o convertir">
        {{ $producto->nombre }} · lote <strong>{{ $lote->etiqueta() }}</strong>
        · {{ $lote->fecha_caducidad ? 'vence '.$lote->fecha_caducidad->format('d/m/Y') : 'sin caducidad' }}
    </x-cabecera-producto>

    <p>
        En este lote:
        @forelse ($presentaciones->filter(fn ($p) => ($enLote[$p->id] ?? 0) !== 0) as $presentacion)
            <span class="badge bg-body-secondary text-body border fs-6 fw-normal me-1">{{ $enLote[$presentacion->id] }} {{ $presentacion->nombre }}</span>
        @empty
            <span class="text-body-secondary">sin existencias</span>
        @endforelse
    </p>

    @if ($errors->has('token'))
        <div class="alert alert-danger">{{ $errors->first('token') }}</div>
    @endif

    <form method="POST" action="{{ route('conversiones.store', $lote) }}" class="card card-body" style="max-width: 40rem" id="form-conversion" data-enter-avanza>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($volverASalida)
            <input type="hidden" name="volver_a_salida" value="1">
        @endif

        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <label for="cantidad" class="form-label">Cantidad</label>
                @include('compartido.cantidad', ['id' => 'cantidad', 'name' => 'cantidad', 'min' => 1, 'valor' => old('cantidad', 1), 'grande' => true, 'requerido' => true])
            </div>
            <div class="col-12 col-sm-6">
                <label for="presentacion_origen_id" class="form-label">De</label>
                <select id="presentacion_origen_id" name="presentacion_origen_id" class="form-select form-select-lg @error('presentacion_origen_id') is-invalid @enderror">
                    @foreach ($presentaciones as $presentacion)
                        <option value="{{ $presentacion->id }}" data-factor="{{ $presentacion->factor }}"
                                @selected((int) old('presentacion_origen_id', $origenInicial) === $presentacion->id)>{{ $presentacion->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @error('cantidad')
                <div class="col-12 text-danger small mt-1">{{ $message }}</div>
            @enderror
            @error('presentacion_origen_id')
                <div class="col-12 text-danger small mt-1">{{ $message }}</div>
            @enderror
            <div class="col-12">
                <label for="presentacion_destino_id" class="form-label">Convertir en</label>
                <select id="presentacion_destino_id" name="presentacion_destino_id" class="form-select form-select-lg @error('presentacion_destino_id') is-invalid @enderror">
                    @foreach ($presentaciones->where('activo', true) as $presentacion)
                        <option value="{{ $presentacion->id }}" data-factor="{{ $presentacion->factor }}"
                                @selected((int) old('presentacion_destino_id', $destinoInicial) === $presentacion->id)>{{ $presentacion->nombre }}</option>
                    @endforeach
                </select>
                @error('presentacion_destino_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="alert alert-info py-2" id="vista-previa" aria-live="polite"></div>

        <div class="mb-3">
            <label for="observacion" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
            <textarea id="observacion" name="observacion" rows="2" maxlength="2000" class="form-control">{{ old('observacion') }}</textarea>
        </div>

        <div class="d-flex gap-2 barra-acciones">
            <button type="submit" class="btn btn-primary btn-lg" id="guardar">Registrar conversión</button>
            <a href="{{ $volverASalida ? route('salidas.create', ['producto' => $producto->id]) : route('inventario.show', $producto) }}"
               class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const cantidad = document.getElementById('cantidad');
            const origen = document.getElementById('presentacion_origen_id');
            const destino = document.getElementById('presentacion_destino_id');
            const vista = document.getElementById('vista-previa');

            // Vista previa; el cálculo válido es el del servidor.
            const actualizar = () => {
                const opcionOrigen = origen.selectedOptions[0];
                const opcionDestino = destino.selectedOptions[0];
                const n = parseInt(cantidad.value, 10);
                if (!opcionOrigen || !opcionDestino || !(n > 0)) { vista.textContent = ''; return; }
                const base = n * Number(opcionOrigen.dataset.factor);
                const resultado = base / Number(opcionDestino.dataset.factor);
                vista.textContent = Number.isInteger(resultado)
                    ? `${n} ${opcionOrigen.text} → ${resultado} ${opcionDestino.text}`
                    : `${n} ${opcionOrigen.text} no forman un número exacto de ${opcionDestino.text}`;
            };

            [cantidad, origen, destino].forEach((campo) => campo.addEventListener('input', actualizar));
            document.getElementById('form-conversion').addEventListener('submit', () => {
                document.getElementById('guardar').disabled = true;
            });
            actualizar();
        })();
    </script>
@endpush
