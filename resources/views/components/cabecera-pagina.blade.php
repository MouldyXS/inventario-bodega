{{-- Cabecera de página: título y, si hay, una línea de contexto (el contenido del componente). --}}
@props(['titulo'])
<div class="mb-3">
    <h1 class="h3 mb-0">{{ $titulo }}</h1>
    @if (! $slot->isEmpty())
        <div class="text-body-secondary">{{ $slot }}</div>
    @endif
</div>
