{{--
    Cantidad con botones − / +. Es el mismo <input type="number"> de siempre (mismo name y validación);
    los botones solo ayudan en pantalla táctil y no entran en el orden de tabulación (con teclado se usa el campo).
    Uso: @include('compartido.cantidad', ['id' => 'cantidad', 'name' => 'cantidad', 'min' => 1, 'valor' => 5, 'error' => ''])
    Opcionales: 'grande' => true, 'etiqueta' => 'texto accesible', 'requerido' => true.
--}}
@php
    $min = $min ?? 1;
    $error = $error ?? '';
    $grande = $grande ?? false;
@endphp
<div @class(['stepper', 'stepper--grande' => $grande])>
    <button type="button" class="stepper__boton" data-stepper="-1" tabindex="-1" aria-label="Restar uno">
        <svg class="icono" aria-hidden="true"><use href="#i-restar"/></svg>
    </button>
    <input type="number" id="{{ $id }}" name="{{ $name }}" min="{{ $min }}" step="1" inputmode="numeric" value="{{ $valor ?? '' }}"
           @if (! empty($etiqueta)) aria-label="{{ $etiqueta }}" @endif @if (! empty($requerido)) required @endif
           @class(['form-control', 'form-control-lg' => $grande, 'is-invalid' => $error !== ''])>
    <button type="button" class="stepper__boton" data-stepper="1" tabindex="-1" aria-label="Sumar uno">
        <svg class="icono" aria-hidden="true"><use href="#i-sumar"/></svg>
    </button>
</div>
@if ($error !== '')
    <div class="invalid-feedback d-block">{{ $error }}</div>
@endif
