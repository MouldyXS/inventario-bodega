{{-- Cabecera de los formularios de movimiento: miniatura, título y una línea de contexto (el contenido del componente). --}}
@props(['producto', 'titulo'])
<div class="cabecera-producto mb-3">
    @include('compartido.miniatura', ['producto' => $producto, 'lado' => 56])
    <div class="min-w-0">
        <h1 class="h3 mb-0">{{ $titulo }}</h1>
        <div class="text-body-secondary">{{ $slot }}</div>
    </div>
</div>
