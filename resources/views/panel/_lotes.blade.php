{{-- Lotes en alerta del panel. $estado: 'vencido' o 'proximo' (filtro de la pantalla de caducidad). --}}
@if ($lotes->isEmpty())
    <p class="text-body-secondary">{{ $vacio }}</p>
@else
    <div class="list-group mb-1">
        @foreach ($lotes as $lote)
            <a href="{{ route('inventario.show', $lote->producto_id) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-2">
                <span>
                    {{ $lote->producto->nombre }}
                    <span class="small text-body-secondary">· lote {{ $lote->etiqueta() }}</span>
                </span>
                <span @class(['small text-nowrap', 'text-danger' => $estado === 'vencido'])>
                    {{ \App\Enums\EstadoCaducidad::textoDias($lote->dias_restantes) }}
                </span>
            </a>
        @endforeach
    </div>
    <a href="{{ route('caducidad.index', ['estado' => $estado]) }}" class="small">Ver todos</a>
@endif
