{{-- Estado de las copias de seguridad (RespaldoService::estado()). --}}
@if (! $respaldo['instalado'])
    <p class="mb-1 text-danger">No hay copias de seguridad registradas: ejecuta <code>scripts\instalar-respaldo.ps1</code> (ver docs/OPERACION.md).</p>
@elseif ($respaldo['ultimo_ok'] === null)
    <p class="mb-1 text-danger">Todavía no hay ninguna copia correcta.</p>
@else
    <p class="mb-1 @if ($respaldo['atrasado']) text-danger @endif">
        Última copia correcta: <strong>{{ $respaldo['ultimo_ok']->format('d/m/Y H:i') }}</strong>
        ({{ $respaldo['antiguedad_horas'] }} h)
        @if ($respaldo['atrasado'])
            — hace más de {{ config('inventario.respaldo.horas_aviso') }} horas.
        @endif
    </p>
@endif
@if ($respaldo['instalado'] && ! $respaldo['ultimo_intento_ok'] && $respaldo['error'])
    <p class="mb-1 text-danger small">Último intento fallido ({{ $respaldo['ultimo_intento']?->format('d/m/Y H:i') }}): {{ $respaldo['error'] }}</p>
@endif
