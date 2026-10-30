@extends('layouts.app')

@section('titulo', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')
    <h1 class="h3 mb-3">{{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>

    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}"
          class="card card-body" style="max-width: 40rem" id="form-usuario">
        @csrf
        @if ($usuario->exists)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="name" class="form-label">Nombre</label>
            <input type="text" id="name" name="name" maxlength="100" value="{{ old('name', $usuario->name) }}"
                   class="form-control @error('name') is-invalid @enderror" required autofocus>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Correo (con él inicia sesión)</label>
            <input type="email" id="email" name="email" maxlength="150" value="{{ old('email', $usuario->email) }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">
                {{ $usuario->exists ? 'Contraseña nueva' : 'Contraseña inicial' }}
                @if ($usuario->exists)
                    <span class="text-body-secondary">(déjala vacía para no cambiarla; si la cambias, se cierran sus sesiones)</span>
                @endif
            </label>
            <input type="password" id="password" name="password" minlength="8" maxlength="100" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror" @required(! $usuario->exists)>
            <div class="form-text">Mínimo 8 caracteres. El usuario puede cambiarla desde su nombre, arriba a la derecha.</div>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select id="rol" name="rol" class="form-select @error('rol') is-invalid @enderror">
                @foreach (\App\Enums\Rol::cases() as $rol)
                    <option value="{{ $rol->value }}" @selected(old('rol', $usuario->rol?->value) === $rol->value)>{{ $rol->etiqueta() }}</option>
                @endforeach
            </select>
            @error('rol')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <fieldset class="mb-3" id="permisos">
            <legend class="form-label fs-6">Permisos especiales <span class="text-body-secondary">(solo para el encargado; el administrador los tiene todos)</span></legend>
            @foreach (['puede_gestionar_catalogo' => 'Gestionar el catálogo (productos, presentaciones, códigos)', 'puede_ajustar_inventario' => 'Ajustar el inventario y corregir lotes', 'puede_revertir' => 'Revertir movimientos'] as $campo => $etiqueta)
                <div class="form-check">
                    <input type="checkbox" id="{{ $campo }}" name="{{ $campo }}" value="1" class="form-check-input"
                           @checked(old($campo, $usuario->{$campo}))>
                    <label for="{{ $campo }}" class="form-check-label">{{ $etiqueta }}</label>
                </div>
            @endforeach
        </fieldset>

        @if ($usuario->exists)
            <div class="form-check mb-3">
                <input type="checkbox" id="activo" name="activo" value="1" class="form-check-input"
                       @checked(old('activo', $usuario->activo))>
                <label for="activo" class="form-check-label">Activo</label>
                @error('activo')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const rol = document.getElementById('rol');
            const permisos = document.getElementById('permisos');
            // Ayuda de interfaz; el servidor ignora los permisos de un administrador.
            const actualizar = () => { permisos.disabled = rol.value === 'admin'; };
            rol.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
@endpush
