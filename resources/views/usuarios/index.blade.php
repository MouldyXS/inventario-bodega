@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary">Nuevo usuario</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle bg-body tabla-apilable" role="table">
                <caption class="visually-hidden">Usuarios</caption>
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col" role="columnheader">Nombre</th>
                    <th scope="col" role="columnheader">Rol y permisos</th>
                    <th scope="col" role="columnheader">Estado</th>
                    <th scope="col" role="columnheader"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @foreach ($usuarios as $usuario)
                    <tr role="row">
                        <td role="cell" data-etiqueta="">
                            {{ $usuario->name }}
                            <div class="small text-body-secondary">{{ $usuario->email }}</div>
                        </td>
                        <td role="cell" data-etiqueta="Rol y permisos">
                            <span class="badge text-bg-primary">{{ $usuario->rol->etiqueta() }}</span>
                            @if ($usuario->esAdmin())
                                <span class="small text-body-secondary">todos los permisos</span>
                            @else
                                @if ($usuario->puede_gestionar_catalogo) <span class="badge bg-body-secondary text-body border">Catálogo</span> @endif
                                @if ($usuario->puede_ajustar_inventario) <span class="badge bg-body-secondary text-body border">Ajustes</span> @endif
                                @if ($usuario->puede_revertir) <span class="badge bg-body-secondary text-body border">Reversiones</span> @endif
                            @endif
                        </td>
                        <td role="cell" data-etiqueta="Estado">
                            @if ($usuario->activo)
                                @include('compartido.estado', ['tipo' => 'activo'])
                            @else
                                @include('compartido.estado', ['tipo' => 'inactivo'])
                            @endif
                        </td>
                        <td role="cell" data-etiqueta="" class="text-end">
                            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-outline-secondary btn-sm">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
