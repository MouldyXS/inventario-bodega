@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="login-pagina">
        <div class="login-tarjeta">
            <div class="text-center mb-4">
                <span class="login-marca" aria-hidden="true"><svg class="icono" aria-hidden="true"><use href="#i-inventario"/></svg></span>
                <h1 class="h4 mt-3 mb-1">{{ config('app.name') }}</h1>
                <div class="text-body-secondary">Inicia sesión para continuar</div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control form-control-lg" autocomplete="username" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            @include('compartido.campo-clave', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'grande' => true])
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
