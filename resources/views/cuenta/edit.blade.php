@extends('layouts.app')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <h1 class="h3 mb-1">Mi cuenta</h1>
    <p class="text-body-secondary">{{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ auth()->user()->rol->etiqueta() }}</p>

    <form method="POST" action="{{ route('cuenta.update') }}" class="card card-body" style="max-width: 32rem">
        @csrf
        @method('PUT')
        <h2 class="h5">Cambiar mi contraseña</h2>

        <div class="mb-3">
            <label for="password_actual" class="form-label">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual" autocomplete="current-password"
                   class="form-control @error('password_actual') is-invalid @enderror" required>
            @error('password_actual')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña nueva <span class="text-body-secondary">(mínimo 8 caracteres)</span></label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Repite la contraseña nueva</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
        </div>
    </form>
@endsection
