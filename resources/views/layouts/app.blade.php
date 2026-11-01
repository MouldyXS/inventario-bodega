<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    {{-- Tema y menú compacto antes de pintar, para evitar el destello: preferencia guardada o, si no hay, la del sistema. --}}
    <script>
        (function () {
            var tema = null;
            try { tema = localStorage.getItem('tema'); } catch (e) {}
            var oscuro = tema === 'oscuro' || (tema !== 'claro' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', oscuro ? 'dark' : 'light');
            try {
                if (localStorage.getItem('menu') === 'compacto') document.documentElement.setAttribute('data-menu', 'compacto');
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    <a class="visually-hidden-focusable saltar-contenido" href="#contenido">Saltar al contenido</a>
    @include('compartido.iconos')

    @auth
        {{-- PC (xl y más): barra lateral fija. --}}
        <aside class="barra-lateral d-none d-xl-flex" aria-label="Menú principal">
            <div class="barra-lateral__cabecera">
                <a class="marca" href="{{ route('inicio') }}" title="{{ config('app.name') }}">{{ config('app.name') }}</a>
                <button type="button" class="btn-icono" data-menu-alternar aria-label="Contraer o ampliar el menú" aria-expanded="true">
                    <svg class="icono" aria-hidden="true"><use href="#i-contraer"/></svg>
                </button>
            </div>
            <nav class="barra-lateral__menu">
                @include('layouts._menu')
            </nav>
        </aside>

        {{-- Tablet y móvil: marca arriba y accesos rápidos abajo. --}}
        <header class="barra-superior d-xl-none">
            <a class="marca" href="{{ route('inicio') }}">{{ config('app.name') }}</a>
        </header>
    @endauth

    <div @class(['app-contenido' => auth()->check()])>
        <main class="container py-4" id="contenido" tabindex="-1">
            @if (session('status'))
                <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 fade show" role="status">
                    <svg class="icono" aria-hidden="true"><use href="#i-exito"/></svg>
                    <div>{{ session('status') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button>
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>

    @auth
        {{-- Las acciones diarias siempre a un toque; «Más» abre el resto del menú. --}}
        <nav class="barra-inferior d-xl-none" aria-label="Accesos rápidos">
            <a href="{{ route('inicio') }}" @class(['active' => request()->routeIs('inicio')]) @if (request()->routeIs('inicio')) aria-current="page" @endif>
                <svg class="icono" aria-hidden="true"><use href="#i-inicio"/></svg><span>Inicio</span>
            </a>
            <a href="{{ route('recepciones.create') }}" @class(['active' => request()->routeIs('recepciones.*')]) @if (request()->routeIs('recepciones.*')) aria-current="page" @endif>
                <svg class="icono" aria-hidden="true"><use href="#i-recibir"/></svg><span>Recibir</span>
            </a>
            <a href="{{ route('salidas.create') }}" @class(['active' => request()->routeIs('salidas.*', 'conversiones.*')]) @if (request()->routeIs('salidas.*', 'conversiones.*')) aria-current="page" @endif>
                <svg class="icono" aria-hidden="true"><use href="#i-retirar"/></svg><span>Retirar</span>
            </a>
            <a href="{{ route('inventario.index') }}" @class(['active' => request()->routeIs('inventario.*')]) @if (request()->routeIs('inventario.*')) aria-current="page" @endif>
                <svg class="icono" aria-hidden="true"><use href="#i-inventario"/></svg><span>Inventario</span>
            </a>
            <button type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-mas" aria-controls="menu-mas">
                <span class="icono-con-aviso">
                    <svg class="icono" aria-hidden="true"><use href="#i-mas"/></svg>
                    @if (($alertasCaducidad ?? 0) > 0)
                        <span class="aviso-punto"><span class="visually-hidden">{{ $alertasCaducidad }} lotes en alerta de caducidad</span></span>
                    @endif
                </span>
                <span>Más</span>
            </button>
        </nav>

        <div class="offcanvas offcanvas-bottom hoja-mas d-xl-none" tabindex="-1" id="menu-mas" aria-labelledby="menu-mas-titulo">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h6 mb-0" id="menu-mas-titulo">Menú</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar el menú"></button>
            </div>
            <nav class="offcanvas-body pt-0">
                @include('layouts._menu')
            </nav>
        </div>
    @endauth

    @stack('scripts')
</body>
</html>
