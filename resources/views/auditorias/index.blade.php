@extends('layouts.app')
@section('titulo', 'Auditoría del Sistema')
@section('contenido')
<div class="alert alert-info"><i class="fas fa-shield-alt"></i> Registro de las últimas 500 acciones sensibles: eliminaciones, ediciones manuales de asistencias y creación de usuarios.</div>
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Descripción</th><th>IP</th></tr></thead>
            <tbody>
            @foreach($auditorias as $a)
                <tr>
                    <td data-order="{{ $a->created_at->timestamp }}">{{ $a->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $a->user?->name ?? 'Sistema' }}</td>
                    <td><span class="badge badge-{{ $a->accion === 'ELIMINAR' ? 'danger' : ($a->accion === 'EDITAR' ? 'warning' : 'success') }}">{{ $a->accion }}</span></td>
                    <td>{{ $a->modulo }}</td>
                    <td>{{ $a->descripcion }}</td>
                    <td class="text-muted">{{ $a->ip }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
