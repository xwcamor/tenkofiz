@extends('layouts.app')
@section('titulo', 'Justificaciones')
@section('boton-header')
    <a href="{{ route('justificaciones.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva justificación</a>
@endsection
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Empleado</th><th>Fecha</th><th>Motivo</th><th>Documento</th><th>Estado</th>@if($esGestor)<th style="width:140px">Acciones</th>@endif</tr></thead>
            <tbody>
            @foreach($justificaciones as $j)
                <tr>
                    <td>{{ $j->empleado->nombre_completo }}</td>
                    <td data-order="{{ $j->fecha->toDateString() }}">{{ $j->fecha->format('d/m/Y') }}</td>
                    <td>{{ $j->motivo }}</td>
                    <td>
                        @if($j->documento)
                            <a href="{{ asset($j->documento) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-alt"></i> Ver</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $j->estado === 'ACEPTADO' ? 'success' : ($j->estado === 'RECHAZADO' ? 'danger' : 'warning') }}">{{ $j->estado }}</span>
                        @if($j->revisor)<div class="text-muted small">por {{ $j->revisor->name }}</div>@endif
                    </td>
                    @if($esGestor)
                    <td>
                        @if($j->estado === 'PENDIENTE')
                            <form method="POST" action="{{ route('justificaciones.estado', $j) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="ACEPTADO">
                                <button class="btn btn-sm btn-success" title="Aceptar (marca el día como JUSTIFICADO)"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="POST" action="{{ route('justificaciones.estado', $j) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="RECHAZADO">
                                <button class="btn btn-sm btn-danger" title="Rechazar"><i class="fas fa-times"></i></button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('justificaciones.destroy', $j) }}" class="d-inline form-eliminar">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-muted mt-2"><i class="fas fa-info-circle"></i> Al <strong>aceptar</strong> una justificación, ese día queda registrado como <span class="badge badge-info">JUSTIFICADO</span> en las asistencias del empleado.</p>
    </div>
</div>
@endsection
