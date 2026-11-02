{{--
    Píldora de estado: icono + texto, nunca solo color.
    Uso: @include('compartido.estado', ['tipo' => 'vencido'])  (opcional: 'texto' => '…' para otra etiqueta)
    Tipos: vencido, proximo, vigente, bajo, activo, inactivo, recomendado, base, inactiva, agotado.
--}}
@php
    [$clase, $icono, $etiqueta] = match ($tipo) {
        'vencido' => ['peligro', 'vencido', 'Vencido'],
        'proximo' => ['aviso', 'reloj', 'Próximo a caducar'],
        'vigente' => ['exito', 'exito', 'Vigente'],
        'bajo' => ['aviso', 'bajo', 'Bajo mínimo'],
        'activo' => ['exito', 'exito', 'Activo'],
        'inactivo' => ['neutro', 'pausa', 'Inactivo'],
        'inactiva' => ['neutro', 'pausa', 'Inactiva'],
        'recomendado' => ['marca', 'estrella', 'Recomendado'],
        'base' => ['marca', 'info', 'Unidad base'],
        'agotado' => ['neutro', 'pausa', 'Sin existencias'],
    };
@endphp
<span class="estado estado--{{ $clase }}"><svg class="icono" aria-hidden="true"><use href="#i-{{ $icono }}"/></svg><span>{{ $texto ?? $etiqueta }}</span></span>
