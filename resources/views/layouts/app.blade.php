<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    @auth
        <nav class="navbar navbar-expand-xl bg-dark" data-bs-theme="dark">
            <div class="container">
                <a class="navbar-brand" href="{{ route('inicio') }}">{{ config('app.name') }}</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                        aria-controls="menu-principal" aria-expanded="false" aria-label="Mostrar menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="menu-principal">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('inventario.*')) active @endif"
                               href="{{ route('inventario.index') }}">Inventario</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('recepciones.*')) active @endif"
                               href="{{ route('recepciones.create') }}">Recepciones</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('salidas.*', 'conversiones.*')) active @endif"
                               href="{{ route('salidas.create') }}">Salidas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('historial.*', 'movimientos.*')) active @endif"
                               href="{{ route('historial.index') }}">Historial</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('caducidad.*')) active @endif"
                               href="{{ route('caducidad.index') }}">
                                Caducidad
                                @if (($alertasCaducidad ?? 0) > 0)
                                    <span class="badge text-bg-warning" title="Lotes vencidos o próximos a caducar">{{ $alertasCaducidad }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('productos.*', 'presentaciones.*')) active @endif"
                               href="{{ route('productos.index') }}">Productos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('categorias.*')) active @endif"
                               href="{{ route('categorias.index') }}">Categorías</a>
                        </li>
                    </ul>
                <div class="d-flex align-items-center gap-3 ms-xl-auto py-2 py-xl-0">
                    <a href="{{ route('cuenta.edit') }}" class="nav-link @if (request()->routeIs('cuenta.*')) active @endif"
                       title="{{ auth()->user()->rol->etiqueta() }} · Cambiar mi contraseña">{{ auth()->user()->name }}</a>
                    @can('administrar')
                        <a href="{{ route('carga-inicial.create') }}" class="nav-link @if (request()->routeIs('carga-inicial.*')) active @endif">Carga inicial</a>
                        <a href="{{ route('usuarios.index') }}" class="nav-link @if (request()->routeIs('usuarios.*')) active @endif">Usuarios</a>
                        <a href="{{ route('configuracion.edit') }}" class="nav-link @if (request()->routeIs('configuracion.*')) active @endif">Configuración</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                    </form>
                </div>
                </div>
            </div>
        </nav>
    @endauth

    <main class="container py-4">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @yield('contenido')
    </main>

    @stack('scripts')
</body>
</html>
