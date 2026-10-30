@extends('layouts.app')

@section('titulo', 'Revisar carga inicial')

@section('contenido')
    <h1 class="h3 mb-1">Revisar carga inicial</h1>
    <p class="text-body-secondary">
        {{ count($filas) }} filas de {{ $productos }} productos. Todavía no se guardó nada.
    </p>

    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>Producto</th><th>Presentación</th><th class="text-end">Cantidad</th><th>Lote</th><th>Caducidad</th><th class="text-end">Costo</th></tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila['producto'] }}</td>
                        <td>{{ $fila['presentacion'] }}</td>
                        <td class="text-end">{{ $fila['cantidad'] }}</td>
                        <td>{{ $fila['codigo_lote'] ?: '—' }}</td>
                        <td>{{ $fila['fecha_caducidad'] ?? '—' }}</td>
                        <td class="text-end">{{ $fila['costo_unitario'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('carga-inicial.store') }}" id="form-carga">
        @csrf
        @if ($previas > 0)
            <div class="alert alert-warning">
                Ya hay {{ $previas }} movimientos de carga inicial.
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="repetir" value="1" id="repetir" required>
                    <label class="form-check-label" for="repetir">Entiendo que se sumarán a las existencias actuales.</label>
                </div>
            </div>
        @endif
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg" id="guardar">Confirmar y registrar</button>
            <a href="{{ route('carga-inicial.create') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-carga').addEventListener('submit', () => {
            const boton = document.getElementById('guardar');
            boton.disabled = true;
            boton.textContent = 'Registrando…';
        });
    </script>
@endpush
