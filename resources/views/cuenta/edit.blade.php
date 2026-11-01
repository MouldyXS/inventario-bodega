@extends('layouts.app')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <x-cabecera-pagina titulo="Mi cuenta">
        {{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ auth()->user()->rol->etiqueta() }}
    </x-cabecera-pagina>

    <form method="POST" action="{{ route('cuenta.update') }}" style="max-width: 32rem">
        @csrf
        @method('PUT')
        <div class="card card-body mb-3">
        <h2 class="h5">Cambiar mi contraseña</h2>

        <div class="mb-3">
            <label for="password_actual" class="form-label">Contraseña actual</label>
            @include('compartido.campo-clave', ['id' => 'password_actual', 'name' => 'password_actual', 'autocomplete' => 'current-password'])
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña nueva <span class="text-body-secondary">(mínimo 8 caracteres)</span></label>
            @include('compartido.campo-clave', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8])
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Repite la contraseña nueva</label>
            @include('compartido.campo-clave', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'autocomplete' => 'new-password'])
        </div>

        </div>

        <div class="d-flex gap-2 barra-acciones">
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
        </div>
    </form>
@endsection
