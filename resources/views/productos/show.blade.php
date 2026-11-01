@extends('layouts.app')

@section('titulo', $producto->nombre)

@section('contenido')
    <section class="card mb-4">
        <div class="card-body d-flex flex-column flex-sm-row gap-3 gap-sm-4">
            {{-- Imagen principal (opcional): todos la ven; solo el administrador la cambia o la quita. --}}
            <div class="ficha-imagen mx-auto mx-sm-0">
                <div class="ficha-imagen__caja">
                    @if ($producto->imagen)
                        <img src="{{ route('productos.imagen', [$producto, $producto->imagen, 'm']) }}" alt="{{ $producto->nombre }}"
                             width="176" height="176" decoding="async">
                    @else
                        <span class="miniatura__marcador" aria-hidden="true">{{ mb_strtoupper(mb_substr($producto->nombre, 0, 1)) }}</span>
                    @endif
                </div>
                @unless ($producto->imagen)
                    <div class="small text-body-secondary text-center mt-1">Este producto no tiene imagen.</div>
                @endunless

                @can('administrar')
                    <details class="mt-2" @if ($errors->has('imagen')) open @endif>
                        <summary class="btn btn-outline-secondary btn-sm w-100">{{ $producto->imagen ? 'Cambiar o quitar imagen' : 'Añadir imagen' }}</summary>
                        <div class="mt-2">
                            @error('imagen')
                                <div class="alert alert-danger small py-2" role="alert">{{ $message }}</div>
                            @enderror
                            <form method="POST" action="{{ route('productos.imagen.update', $producto) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <label for="imagen" class="visually-hidden">Imagen del producto</label>
                                <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp" required class="form-control form-control-sm mb-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">{{ $producto->imagen ? 'Cambiar imagen' : 'Subir imagen' }}</button>
                                <div class="form-text">JPEG, PNG o WebP de hasta 8 MB.</div>
                            </form>
                            @if ($producto->imagen)
                                <form method="POST" action="{{ route('productos.imagen.destroy', $producto) }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">Quitar imagen</button>
                                </form>
                            @endif
                        </div>
                    </details>
                @endcan
            </div>

            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h1 class="h3 mb-1">{{ $producto->nombre }}</h1>
                        <div class="text-body-secondary d-flex flex-wrap align-items-center gap-2">
                            <span>{{ $producto->marca ?: 'Sin marca' }} · {{ $producto->categoria->nombre }}</span>
                            @unless ($producto->activo)
                                @include('compartido.estado', ['tipo' => 'inactivo'])
                            @endunless
                        </div>
                    </div>
                    @can('gestionar-catalogo')
                        <a href="{{ route('productos.edit', $producto) }}" class="btn btn-outline-secondary">Editar producto</a>
                    @endcan
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-5">Control de caducidad</dt>
                    <dd class="col-sm-7">{{ $producto->controla_caducidad ? 'Sí' : 'No' }}</dd>
                    <dt class="col-sm-5">Existencias mínimas</dt>
                    <dd class="col-sm-7">
                        {{ $producto->stock_minimo !== null ? $producto->stock_minimo.' '.$producto->presentaciones->firstWhere('es_base')?->nombre : 'Sin definir' }}
                    </dd>
                    @if ($producto->observaciones)
                        <dt class="col-sm-5">Observaciones</dt>
                        <dd class="col-sm-7">{{ $producto->observaciones }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </section>

    <h2 class="h5" id="presentaciones">Presentaciones</h2>

    @foreach ($producto->presentaciones as $presentacion)
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong>{{ $presentacion->nombre }}</strong>
                        @if ($presentacion->es_base)
                            @include('compartido.estado', ['tipo' => 'base'])
                        @else
                            <span class="text-body-secondary">= {{ $presentacion->factor }} {{ $producto->presentaciones->firstWhere('es_base')?->nombre }}</span>
                        @endif
                        @unless ($presentacion->activo)
                            @include('compartido.estado', ['tipo' => 'inactiva'])
                        @endunless
                    </div>
                    @can('gestionar-catalogo')
                        <a href="{{ route('presentaciones.edit', $presentacion) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                    @endcan
                </div>

                <div class="mt-2">
                    <span class="text-body-secondary small">Códigos de barras:</span>
                    @forelse ($presentacion->codigos as $codigo)
                        <span class="badge bg-body-secondary text-body border font-monospace fs-6 fw-normal me-1">
                            {{ $codigo->codigo }}
                            @can('gestionar-catalogo')
                                <form method="POST" action="{{ route('codigos.destroy', $codigo) }}" class="d-inline"
                                      onsubmit="return confirm('¿Quitar el código {{ $codigo->codigo }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link px-2 py-0 text-danger text-decoration-none fs-5 lh-1"
                                            aria-label="Quitar código {{ $codigo->codigo }}">×</button>
                                </form>
                            @endcan
                        </span>
                    @empty
                        <span class="text-body-secondary small">ninguno</span>
                    @endforelse
                </div>

                @can('gestionar-catalogo')
                    @php($bolsa = 'codigo_'.$presentacion->id)
                    <form method="POST" action="{{ route('codigos.store', $presentacion) }}" class="row g-2 mt-1">
                        @csrf
                        <div class="col-8 col-md-5">
                            <label for="codigo-{{ $presentacion->id }}" class="visually-hidden">Código para {{ $presentacion->nombre }}</label>
                            <input type="text" id="codigo-{{ $presentacion->id }}" name="codigo" maxlength="64" inputmode="text"
                                   placeholder="Escribir o escanear código"
                                   value="{{ $errors->getBag($bolsa)->any() ? old('codigo') : '' }}"
                                   class="form-control form-control-sm font-monospace @if ($errors->getBag($bolsa)->has('codigo')) is-invalid @endif">
                            @if ($errors->getBag($bolsa)->has('codigo'))
                                <div class="invalid-feedback">{{ $errors->getBag($bolsa)->first('codigo') }}</div>
                            @endif
                        </div>
                        <div class="col-4 col-md-3 d-grid">
                            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">Añadir código</button>
                        </div>
                    </form>
                @endcan
            </div>
        </div>
    @endforeach

    @can('gestionar-catalogo')
        <div class="card border-primary-subtle">
            <div class="card-body">
                <h3 class="h6">Añadir presentación</h3>
                <form method="POST" action="{{ route('presentaciones.store', $producto) }}" class="row g-2">
                    @csrf
                    <div class="col-12 col-md-5">
                        <label for="presentacion-nombre" class="form-label small">Nombre</label>
                        <input type="text" id="presentacion-nombre" name="nombre" maxlength="50" placeholder="Caja x12"
                               value="{{ $errors->presentacion->any() ? old('nombre') : '' }}"
                               class="form-control @if ($errors->presentacion->has('nombre')) is-invalid @endif">
                        @if ($errors->presentacion->has('nombre'))
                            <div class="invalid-feedback">{{ $errors->presentacion->first('nombre') }}</div>
                        @endif
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="presentacion-factor" class="form-label small">
                            Contiene ({{ $producto->presentaciones->firstWhere('es_base')?->nombre }})
                        </label>
                        <input type="number" id="presentacion-factor" name="factor" min="1" step="1" placeholder="12"
                               value="{{ $errors->presentacion->any() ? old('factor') : '' }}"
                               class="form-control @if ($errors->presentacion->has('factor')) is-invalid @endif">
                        @if ($errors->presentacion->has('factor'))
                            <div class="invalid-feedback">{{ $errors->presentacion->first('factor') }}</div>
                        @endif
                    </div>
                    <div class="col-6 col-md-4 d-grid align-items-end">
                        <button type="submit" class="btn btn-primary mt-md-4">Añadir presentación</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <a href="{{ route('productos.index') }}" class="btn btn-link px-0 mt-3">← Volver a productos</a>
@endsection
