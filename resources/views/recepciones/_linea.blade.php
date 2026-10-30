{{-- Una línea de la recepción. $i es el índice o '__i__' en la plantilla. --}}
@php($error = fn (string $campo) => is_int($i) ? $errors->first("lineas.$i.$campo") : '')
<div class="card mb-2" data-linea>
    <div class="card-body row g-2 align-items-start">
        <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label small mb-1" for="linea-{{ $i }}-presentacion">Presentación</label>
            <select id="linea-{{ $i }}-presentacion" name="lineas[{{ $i }}][presentacion_id]"
                    class="form-select @if ($error('presentacion_id')) is-invalid @endif">
                @foreach ($presentaciones as $presentacion)
                    <option value="{{ $presentacion->id }}" @selected((int) ($linea['presentacion_id'] ?? 0) === $presentacion->id)>
                        {{ $presentacion->nombre }}@unless ($presentacion->es_base) ({{ $presentacion->factor }})@endunless
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $error('presentacion_id') }}</div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small mb-1" for="linea-{{ $i }}-cantidad">Cantidad</label>
            <input type="number" id="linea-{{ $i }}-cantidad" name="lineas[{{ $i }}][cantidad]" min="1" step="1"
                   inputmode="numeric" value="{{ $linea['cantidad'] ?? '' }}"
                   class="form-control @if ($error('cantidad')) is-invalid @endif">
            <div class="invalid-feedback">{{ $error('cantidad') }}</div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small mb-1" for="linea-{{ $i }}-lote">Código de lote</label>
            <input type="text" id="linea-{{ $i }}-lote" name="lineas[{{ $i }}][codigo_lote]" maxlength="50"
                   value="{{ $linea['codigo_lote'] ?? '' }}" placeholder="Opcional"
                   class="form-control @if ($error('codigo_lote')) is-invalid @endif">
            <div class="invalid-feedback">{{ $error('codigo_lote') }}</div>
        </div>
        @if ($producto->controla_caducidad)
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small mb-1" for="linea-{{ $i }}-caducidad">Caducidad</label>
                <input type="date" id="linea-{{ $i }}-caducidad" name="lineas[{{ $i }}][fecha_caducidad]"
                       value="{{ $linea['fecha_caducidad'] ?? '' }}"
                       class="form-control @if ($error('fecha_caducidad')) is-invalid @endif">
                <div class="invalid-feedback">{{ $error('fecha_caducidad') }}</div>
            </div>
        @endif
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small mb-1" for="linea-{{ $i }}-costo">Costo unitario</label>
            <input type="number" id="linea-{{ $i }}-costo" name="lineas[{{ $i }}][costo_unitario]" min="0" step="0.0001"
                   inputmode="decimal" value="{{ $linea['costo_unitario'] ?? '' }}" placeholder="Opcional"
                   class="form-control @if ($error('costo_unitario')) is-invalid @endif">
            <div class="invalid-feedback">{{ $error('costo_unitario') }}</div>
        </div>
        <div class="col-12 text-end">
            <button type="button" class="btn btn-link btn-sm text-danger p-0" data-quitar-linea>Quitar línea</button>
        </div>
    </div>
</div>
