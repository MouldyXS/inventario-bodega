{{-- Una línea de la salida. $i es el índice o '__i__' en la plantilla. --}}
@php($error = fn (string $campo) => is_int($i) ? $errors->first("lineas.$i.$campo") : '')
<div class="card mb-2 linea-movimiento" data-linea>
    <div class="card-body row g-2 align-items-start">
        <div class="col-12 col-lg-5">
            <label class="form-label small mb-1" for="linea-{{ $i }}-lote">Lote</label>
            <select id="linea-{{ $i }}-lote" name="lineas[{{ $i }}][lote_id]"
                    class="form-select @if ($error('lote_id')) is-invalid @endif">
                <option value="">Elige el lote…</option>
                @foreach ($lotes as $lote)
                    @php($vencido = $existencias->estaVencido($lote))
                    <option value="{{ $lote->id }}" @if ($vencido) data-vencido @endif
                            @selected((int) ($linea['lote_id'] ?? 0) === $lote->id)>
                        {{ $lote->etiqueta() }}
                        · {{ $lote->fecha_caducidad ? 'vence '.$lote->fecha_caducidad->format('d/m/Y') : 'sin caducidad' }}
                        · {{ $porLote->get($lote->id, collect())->map(fn ($f) => $f->cantidad.' '.$presentaciones[$f->presentacion_id]->nombre)->join(', ') }}
                        @if ($vencido) — VENCIDO @elseif ($lote->id === $recomendadoId) (recomendado) @endif
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $error('lote_id') }}</div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small mb-1" for="linea-{{ $i }}-presentacion">Presentación</label>
            <select id="linea-{{ $i }}-presentacion" name="lineas[{{ $i }}][presentacion_id]"
                    class="form-select @if ($error('presentacion_id')) is-invalid @endif">
                @foreach ($presentaciones->where('activo', true) as $presentacion)
                    <option value="{{ $presentacion->id }}"
                            @selected((int) ($linea['presentacion_id'] ?? $presentacionInicial ?? 0) === $presentacion->id)>
                        {{ $presentacion->nombre }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $error('presentacion_id') }}</div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small mb-1" for="linea-{{ $i }}-cantidad">Cantidad</label>
            @include('compartido.cantidad', ['id' => "linea-{$i}-cantidad", 'name' => "lineas[{$i}][cantidad]", 'min' => 1, 'valor' => $linea['cantidad'] ?? '', 'error' => $error('cantidad')])
        </div>
        <div class="col-12 col-lg-1 text-end">
            <button type="button" class="btn btn-link btn-sm text-danger mt-lg-3" data-quitar-linea>Quitar</button>
        </div>
    </div>
</div>
