{{-- Menú principal: una sola definición para la barra lateral (PC) y la hoja «Más» (tablet y móvil). --}}
@php
    $secciones = [
        'Operar' => [
            ['recepciones.create', 'Recibir', 'recibir', ['recepciones.*']],
            ['salidas.create', 'Retirar', 'retirar', ['salidas.*', 'conversiones.*']],
        ],
        'Consultar' => [
            ['inventario.index', 'Inventario', 'inventario', ['inventario.*']],
            ['caducidad.index', 'Caducidad', 'caducidad', ['caducidad.*'], true],
            ['historial.index', 'Historial', 'historial', ['historial.*', 'movimientos.*']],
        ],
        'Catálogo' => [
            ['productos.index', 'Productos', 'productos', ['productos.*', 'presentaciones.*']],
            ['categorias.index', 'Categorías', 'categorias', ['categorias.*']],
        ],
    ];

    if (auth()->user()->can('administrar')) {
        $secciones['Administración'] = [
            ['carga-inicial.create', 'Carga inicial', 'carga', ['carga-inicial.*']],
            ['usuarios.index', 'Usuarios', 'usuarios', ['usuarios.*']],
            ['configuracion.edit', 'Configuración', 'configuracion', ['configuracion.*']],
        ];
    }
@endphp

<a href="{{ route('inicio') }}" class="enlace-menu @if (request()->routeIs('inicio')) active @endif"
   title="Inicio" @if (request()->routeIs('inicio')) aria-current="page" @endif>
    <svg class="icono" aria-hidden="true"><use href="#i-inicio"/></svg><span class="enlace-menu__texto">Inicio</span>
</a>

@foreach ($secciones as $titulo => $enlaces)
    <div class="titulo-seccion"><span class="enlace-menu__texto">{{ $titulo }}</span></div>
    @foreach ($enlaces as $enlace)
        @php
            [$ruta, $etiqueta, $icono, $activas] = $enlace;
            $conAlertas = $enlace[4] ?? false;
            $activo = request()->routeIs(...$activas);
        @endphp
        <a href="{{ route($ruta) }}" class="enlace-menu @if ($activo) active @endif" title="{{ $etiqueta }}"
           @if ($activo) aria-current="page" @endif>
            <svg class="icono" aria-hidden="true"><use href="#i-{{ $icono }}"/></svg><span class="enlace-menu__texto">{{ $etiqueta }}</span>
            @if ($conAlertas && ($alertasCaducidad ?? 0) > 0)
                <span class="badge text-bg-warning ms-auto" title="Lotes vencidos o próximos a caducar">{{ $alertasCaducidad }}<span class="visually-hidden"> lotes en alerta</span></span>
            @endif
        </a>
    @endforeach
@endforeach

<div class="pie-menu">
    <a href="{{ route('cuenta.edit') }}" class="enlace-menu @if (request()->routeIs('cuenta.*')) active @endif"
       title="{{ auth()->user()->rol->etiqueta() }} · Cambiar mi contraseña">
        <svg class="icono" aria-hidden="true"><use href="#i-cuenta"/></svg>
        <span class="enlace-menu__texto">{{ auth()->user()->name }}</span>
    </a>
    <button type="button" class="enlace-menu" data-tema-alternar title="Cambiar el tema: automático, claro u oscuro">
        <svg class="icono" aria-hidden="true"><use href="#i-tema"/></svg>
        <span class="enlace-menu__texto" data-tema-etiqueta>Tema: automático</span>
    </button>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="enlace-menu" title="Cerrar sesión">
            <svg class="icono" aria-hidden="true"><use href="#i-salir"/></svg><span class="enlace-menu__texto">Cerrar sesión</span>
        </button>
    </form>
</div>
