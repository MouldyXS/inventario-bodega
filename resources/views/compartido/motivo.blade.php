{{--
    Motivo como botones segmentados: radios con el mismo name="motivo" y los mismos valores que el desplegable anterior.
    Uso: @include('compartido.motivo', ['motivos' => $motivos, 'seleccionado' => old('motivo', 'compra')])
    El elegido lleva una marca ✓ además del color.
--}}
<fieldset class="mb-0">
    <legend class="form-label fs-6">Motivo</legend>
    <div class="segmentado">
        @foreach ($motivos as $opcion)
            <input type="radio" class="btn-check" name="motivo" id="motivo-{{ $opcion->value }}" value="{{ $opcion->value }}"
                   autocomplete="off" @checked($seleccionado === $opcion->value) @if ($loop->first) required @endif>
            <label class="btn btn-outline-primary" for="motivo-{{ $opcion->value }}">{{ $opcion->etiqueta() }}</label>
        @endforeach
    </div>
    @error('motivo')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</fieldset>
