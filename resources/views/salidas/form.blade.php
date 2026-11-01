@extends('layouts.app')

@section('titulo', 'Nueva salida')

@section('contenido')
    <x-cabecera-producto :producto="$producto" titulo="Nueva salida">
        <strong>{{ $producto->nombre }}</strong> · {{ $producto->marca ?: 'Sin marca' }}
        · <a href="{{ route('salidas.create') }}">Cambiar producto</a>
    </x-cabecera-producto>

    @if ($lotes->isEmpty())
        <div class="alert alert-warning">Este producto no tiene existencias.</div>
    @else
        {{-- RF-07: lotes en orden FEFO; el recomendado se señala, no se elige solo. --}}
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle bg-body mb-0 tabla-apilable" role="table">
                <caption class="visually-hidden">Lotes disponibles, por orden de caducidad</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Lote</th>
                        <th scope="col" role="columnheader">Caducidad</th>
                        <th scope="col" role="columnheader">Existencias</th>
                        <th scope="col" role="columnheader"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    @foreach ($lotes as $lote)
                        <tr role="row" @class(['table-danger' => $existencias->estaVencido($lote), 'table-success' => $lote->id === $recomendadoId])>
                            <td role="cell" data-etiqueta="">
                                {{ $lote->etiqueta() }}
                                @if ($lote->id === $recomendadoId)
                                    @include('compartido.estado', ['tipo' => 'recomendado'])
                                @endif
                            </td>
                            <td role="cell" data-etiqueta="Caducidad">
                                {{ $lote->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}
                                @if ($existencias->estaVencido($lote))
                                    @include('compartido.estado', ['tipo' => 'vencido'])
                                @endif
                            </td>
                            <td role="cell" data-etiqueta="Existencias">{{ $porLote->get($lote->id, collect())->map(fn ($f) => $f->cantidad.' '.$presentaciones[$f->presentacion_id]->nombre)->join(', ') }}</td>
                            <td role="cell" data-etiqueta="" class="text-end">
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
                <div class="col-12 col-md-8">
                    @include('compartido.motivo', ['motivos' => $motivos, 'seleccionado' => old('motivo', $motivoInicial->value)])
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

            <div class="d-flex gap-2 barra-acciones">
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
            let siguiente = {{ count(old('lineas', [[]])) }} + 1000;

            // RF-07: los lotes vencidos solo se pueden elegir con motivo vencido o dañado.
            // Es una ayuda de interfaz; el servidor lo comprueba siempre.
            const actualizarVencidos = () => {
                const permitido = ['vencido', 'danado'].includes(formulario.elements.motivo.value);
                lineas.querySelectorAll('option[data-vencido]').forEach((opcion) => {
                    opcion.disabled = !permitido;
                    if (!permitido && opcion.selected) opcion.parentElement.value = '';
                });
            };

            formulario.addEventListener('change', (evento) => {
                if (evento.target.name === 'motivo') actualizarVencidos();
            });

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
