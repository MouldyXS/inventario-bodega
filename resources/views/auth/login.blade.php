@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">
            <div class="card shadow-sm mt-md-5">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4 text-center">{{ config('app.name') }}</h1>

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
                            <input type="password" id="password" name="password"
                                   class="form-control form-control-lg" autocomplete="current-password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
