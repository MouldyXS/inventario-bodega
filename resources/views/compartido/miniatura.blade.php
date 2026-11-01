{{--
    Miniatura de un producto, con marcador (inicial) si no tiene imagen. Tamaño fijo para que la
    página no se mueva al cargar. Para listados, búsquedas y cabeceras; usa la versión -t (160 px).
    Uso: @include('compartido.miniatura', ['producto' => $producto, 'lado' => 48])
    Es decorativa (alt vacío) porque el nombre va al lado; con 'decorativa' => false lleva el nombre como alt.
--}}
@php
    $lado = $lado ?? 48;
    $decorativa = $decorativa ?? true;
@endphp
<span class="miniatura" style="--lado: {{ $lado }}px">
    @if ($producto->imagen)
        <img src="{{ route('productos.imagen', [$producto, $producto->imagen, 't']) }}" alt="{{ $decorativa ? '' : $producto->nombre }}"
             width="{{ $lado }}" height="{{ $lado }}" loading="lazy" decoding="async">
    @else
        <span class="miniatura__marcador" aria-hidden="true">{{ mb_strtoupper(mb_substr($producto->nombre, 0, 1)) }}</span>
    @endif
</span>
