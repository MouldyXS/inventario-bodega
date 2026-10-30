@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary">Nuevo usuario</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle bg-white">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Rol y permisos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($usuarios as $usuario)
                    <tr>
                        <td>
                            {{ $usuario->name }}
                            <div class="small text-body-secondary">{{ $usuario->email }}</div>
                        </td>
                        <td>
                            <span class="badge text-bg-primary">{{ $usuario->rol->etiqueta() }}</span>
                            @if ($usuario->esAdmin())
                                <span class="small text-body-secondary">todos los permisos</span>
                            @else
                                @if ($usuario->puede_gestionar_catalogo) <span class="badge text-bg-light border">Catálogo</span> @endif
                                @if ($usuario->puede_ajustar_inventario) <span class="badge text-bg-light border">Ajustes</span> @endif
                                @if ($usuario->puede_revertir) <span class="badge text-bg-light border">Reversiones</span> @endif
                            @endif
                        </td>
                        <td>
                            @if ($usuario->activo)
                                <span class="badge text-bg-success">Activo</span>
                            @else
                                <span class="badge text-bg-secondary">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
