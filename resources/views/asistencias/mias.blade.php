@extends('layouts.app')
@section('titulo', 'Mis Asistencias')
@section('contenido')
@if(!$empleado)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Su usuario no está vinculado a un empleado. Solicite al administrador que lo vincule.</div>
@else
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-user-check"></i> Historial — {{ $empleado->nombre_completo }}</h3></div>
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
            <tbody>
            @foreach($asistencias as $a)
                <tr>
                    <td>{{ $a->fecha->format('d/m/Y') }}</td>
                    <td>{{ $a->hora_entrada ?? '—' }}</td>
                    <td>{{ $a->hora_salida ?? '—' }}</td>
                    <td><span class="badge badge-{{ $a->estado === 'PUNTUAL' ? 'success' : ($a->estado === 'TARDANZA' ? 'warning' : 'secondary') }}">{{ $a->estado }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
