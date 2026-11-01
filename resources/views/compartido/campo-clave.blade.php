{{--
    Contraseña con botón «Mostrar / Ocultar» (útil en la tablet). Mismo <input type="password"> y mismos nombres de siempre.
    Uso: @include('compartido.campo-clave', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password'])
    Opcionales: 'requerido' => false, 'minlength' => 8, 'maxlength' => 100, 'grande' => true, 'autofocus' => true.
--}}
<div @class(['input-group', 'input-group-lg' => $grande ?? false, 'has-validation' => $errors->has($name)])>
    <input type="password" id="{{ $id }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}"
           @if (! empty($minlength)) minlength="{{ $minlength }}" @endif @if (! empty($maxlength)) maxlength="{{ $maxlength }}" @endif
           @if ($requerido ?? true) required @endif @if (! empty($autofocus)) autofocus @endif
           @class(['form-control', 'is-invalid' => $errors->has($name)])>
    <button type="button" class="btn btn-outline-secondary" data-mostrar-clave aria-controls="{{ $id }}" aria-pressed="false">Mostrar</button>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
