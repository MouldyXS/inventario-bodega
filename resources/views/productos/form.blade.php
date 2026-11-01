@extends('layouts.app')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 mb-0">{{ $producto->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
        @if ($producto->exists)
            <div class="text-body-secondary">{{ $producto->nombre }}</div>
        @else
            <div class="text-body-secondary">La imagen se añade después, desde la ficha del producto.</div>
        @endif
    </div>

    @if ($categorias->isEmpty())
        <div class="alert alert-warning">
            Antes de registrar productos hay que crear al menos una categoría.
            <a href="{{ route('categorias.create') }}" class="alert-link">Crear categoría</a>
        </div>
    @else
        @if (session('duplicados'))
            <div class="alert alert-warning">
                <p class="mb-2"><strong>Ya existe un producto con el mismo nombre y marca:</strong></p>
                <ul class="mb-2">
                    @foreach (session('duplicados') as $duplicado)
                        <li>
                            <a href="{{ route('productos.show', $duplicado['id']) }}" target="_blank">{{ $duplicado['nombre'] }}</a>
                            @if ($duplicado['marca'])
                                · {{ $duplicado['marca'] }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mb-0">Si es una variante distinta, incluye la variante y el contenido en el nombre
                    (por ejemplo, "Aceite de girasol 1 L"). Si de verdad es otro producto, confirma abajo.</p>
            </div>
        @endif

        <form method="POST" action="{{ $producto->exists ? route('productos.update', $producto) : route('productos.store') }}"
              style="max-width: 48rem">
            @csrf
            @if ($producto->exists)
                @method('PUT')
            @endif

            <section class="card card-body mb-3" aria-labelledby="datos-titulo">
            <h2 class="h6 mb-3" id="datos-titulo">Datos del producto</h2>

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre descriptivo</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $producto->nombre) }}" maxlength="150"
                       class="form-control @error('nombre') is-invalid @enderror" required autofocus>
                <div class="form-text">Incluye la variante y el contenido: "Aceite de girasol 1 L".</div>
                @error('nombre')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="marca" class="form-label">Marca <span class="text-body-secondary">(opcional)</span></label>
                    <input type="text" id="marca" name="marca" value="{{ old('marca', $producto->marca) }}" maxlength="100"
                           class="form-control @error('marca') is-invalid @enderror">
                    @error('marca')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="categoria_id" class="form-label">Categoría</label>
                    <select id="categoria_id" name="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror" required>
                        <option value="">Elige…</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" @selected((int) old('categoria_id', $producto->categoria_id) === $categoria->id)>
                                {{ $categoria->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('categoria_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @unless ($producto->exists)
                <div>
                    <label for="nombre_presentacion_base" class="form-label">Unidad base</label>
                    <input type="text" id="nombre_presentacion_base" name="nombre_presentacion_base" maxlength="50"
                           value="{{ old('nombre_presentacion_base') }}" placeholder="Botella, unidad, paquete…"
                           class="form-control @error('nombre_presentacion_base') is-invalid @enderror" required>
                    <div class="form-text">Es la presentación en la que se cuentan las existencias (factor 1). Las cajas se añaden después.</div>
                    @error('nombre_presentacion_base')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endunless
            </section>

            <section class="card card-body mb-3" aria-labelledby="control-titulo">
            <h2 class="h6 mb-3" id="control-titulo">Existencias y control</h2>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="stock_minimo" class="form-label">Existencias mínimas <span class="text-body-secondary">(opcional, en unidades base)</span></label>
                    <input type="number" id="stock_minimo" name="stock_minimo" min="0" step="1"
                           value="{{ old('stock_minimo', $producto->stock_minimo) }}"
                           class="form-control @error('stock_minimo') is-invalid @enderror">
                    @error('stock_minimo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 d-flex flex-column justify-content-end gap-2">
                    <div class="form-check">
                        <input type="checkbox" id="controla_caducidad" name="controla_caducidad" value="1" class="form-check-input"
                               @checked(old('controla_caducidad', $producto->exists ? $producto->controla_caducidad : true))>
                        <label for="controla_caducidad" class="form-check-label">Controla fecha de caducidad</label>
                        @error('controla_caducidad')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    @if ($producto->exists)
                        <div class="form-check">
                            <input type="checkbox" id="activo" name="activo" value="1" class="form-check-input"
                                   @checked(old('activo', $producto->activo))>
                            <label for="activo" class="form-check-label">Activo</label>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000"
                          class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $producto->observaciones) }}</textarea>
                @error('observaciones')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            </section>

            @if (session('duplicados'))
                <div class="form-check mb-3">
                    <input type="checkbox" id="confirmar_duplicado" name="confirmar_duplicado" value="1" class="form-check-input">
                    <label for="confirmar_duplicado" class="form-check-label">Sí, es un producto distinto: crearlo de todas formas</label>
                </div>
            @endif

            <div class="d-flex gap-2 barra-acciones">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ $producto->exists ? route('productos.show', $producto) : route('productos.index') }}"
                   class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    @endif
@endsection
