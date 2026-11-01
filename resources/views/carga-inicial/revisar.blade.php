@extends('layouts.app')

@section('titulo', 'Revisar carga inicial')

@section('contenido')
    <x-cabecera-pagina titulo="Revisar carga inicial">
        {{ count($filas) }} filas de {{ $productos }} productos. Todavía no se guardó nada.
    </x-cabecera-pagina>

    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle tabla-apilable" role="table">
                <caption class="visually-hidden">Filas del archivo a cargar</caption>
            <thead role="rowgroup">
                <tr role="row"><th scope="col" role="columnheader">Producto</th><th scope="col" role="columnheader">Presentación</th><th scope="col" role="columnheader" class="text-end">Cantidad</th><th scope="col" role="columnheader">Lote</th><th scope="col" role="columnheader">Caducidad</th><th scope="col" role="columnheader" class="text-end">Costo</th></tr>
            </thead>
            <tbody role="rowgroup">
                @foreach ($filas as $fila)
                    <tr role="row">
                        <td role="cell" data-etiqueta="">{{ $fila['producto'] }}</td>
                        <td role="cell" data-etiqueta="Presentación">{{ $fila['presentacion'] }}</td>
                        <td role="cell" data-etiqueta="Cantidad" class="text-end">{{ $fila['cantidad'] }}</td>
                        <td role="cell" data-etiqueta="Lote">{{ $fila['codigo_lote'] ?: '—' }}</td>
                        <td role="cell" data-etiqueta="Caducidad">{{ $fila['fecha_caducidad'] ?? '—' }}</td>
                        <td role="cell" data-etiqueta="Costo" class="text-end">{{ $fila['costo_unitario'] ?? '—' }}</td>
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
        <div class="d-flex gap-2 barra-acciones">
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
