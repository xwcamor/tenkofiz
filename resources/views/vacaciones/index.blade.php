@extends('layouts.app')
@section('titulo', 'Vacaciones')
@section('boton-header')
    <a href="{{ route('vacaciones.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Solicitar vacaciones</a>
@endsection
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Empleado</th><th>Inicio</th><th>Fin</th><th>Días</th><th>Motivo</th><th>Estado</th>@if($esGestor)<th style="width:110px">Acciones</th>@endif</tr></thead>
            <tbody>
            @foreach($vacaciones as $v)
                <tr>
                    <td>{{ $v->empleado->nombre_completo }}</td>
                    <td>{{ $v->fecha_inicio->format('d/m/Y') }}</td>
                    <td>{{ $v->fecha_fin->format('d/m/Y') }}</td>
                    <td>{{ $v->dias }}</td>
                    <td class="text-muted">{{ $v->motivo }}</td>
                    <td>
                        <span class="badge badge-{{ $v->estado === 'APROBADO' ? 'success' : ($v->estado === 'RECHAZADO' ? 'danger' : 'warning') }}">{{ $v->estado }}</span>
                        @if($v->aprobador)<div class="text-muted small">por {{ $v->aprobador->name }}</div>@endif
                    </td>
                    @if($esGestor)
                    <td>
                        @if($v->estado === 'PENDIENTE')
                            <form method="POST" action="{{ route('vacaciones.estado', $v) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="APROBADO">
                                <button class="btn btn-sm btn-success" title="Aprobar"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('vacaciones.estado', $v) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="RECHAZADO">
                                <button class="btn btn-sm btn-danger" title="Rechazar"><i class="fas fa-times"></i></button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
