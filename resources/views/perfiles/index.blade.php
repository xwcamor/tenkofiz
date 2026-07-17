@extends('layouts.app')
@section('titulo', 'Perfiles')
@section('boton-header')
    <a href="{{ route('perfiles.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo perfil</a>
@endsection
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Nombre</th><th>Descripción</th><th>Usuarios</th><th>Estado</th><th style="width:110px">Acciones</th></tr></thead>
            <tbody>
            @foreach($perfiles as $p)
                <tr>
                    <td><strong>{{ $p->nombre }}</strong></td>
                    <td>{{ $p->descripcion }}</td>
                    <td><span class="badge badge-info">{{ $p->users_count }}</span></td>
                    <td><span class="badge badge-{{ $p->activo ? 'success' : 'secondary' }}">{{ $p->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        <a href="{{ route('perfiles.edit', $p) }}" class="btn btn-sm btn-info"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('perfiles.destroy', $p) }}" class="d-inline form-eliminar">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
