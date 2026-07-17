@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('boton-header')
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo usuario</a>
@endsection
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead>
                <tr><th>Nombre</th><th>Correo</th><th>Perfil</th><th>Estado</th><th style="width:110px">Acciones</th></tr>
            </thead>
            <tbody>
            @foreach($usuarios as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge badge-primary">{{ $u->perfil?->nombre ?? '—' }}</span></td>
                    <td><span class="badge badge-{{ $u->activo ? 'success' : 'secondary' }}">{{ $u->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-info" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('usuarios.destroy', $u) }}" class="d-inline form-eliminar">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
