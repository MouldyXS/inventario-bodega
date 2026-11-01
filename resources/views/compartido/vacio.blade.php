{{--
    Estado vacío: explica qué pasa y, si hay filtros activos, ofrece quitarlos.
    Uso: @include('compartido.vacio', ['mensaje' => '…', 'quitar' => route('…')])   ('quitar' solo si hay filtros activos)
--}}
<div class="estado-vacio" role="status">
    <svg class="icono" aria-hidden="true"><use href="#i-info"/></svg>
    <p class="mb-0">{{ $mensaje }}</p>
    @if (! empty($quitar))
        <a href="{{ $quitar }}" class="btn btn-outline-primary btn-sm mt-3">Quitar filtros</a>
    @endif
</div>
