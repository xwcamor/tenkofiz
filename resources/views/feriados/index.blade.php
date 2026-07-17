@extends('layouts.app')
@section('titulo', 'Feriados')
@section('boton-header')
    <div>
        <form method="POST" action="{{ route('feriados.generar') }}" class="d-inline-flex align-items-center mr-2">
            @csrf
            <input type="number" name="anio" value="{{ now()->addYear()->year }}" min="2020" max="2100" class="form-control form-control-sm mr-1" style="width:90px">
            <button class="btn btn-success btn-sm" title="Genera automáticamente los feriados nacionales del Perú, incluida Semana Santa"><i class="fas fa-magic"></i> Generar año</button>
        </form>
        <a href="{{ route('feriados.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nuevo feriado</a>
    </div>
@endsection
@section('contenido')
<div class="alert alert-info"><i class="fas fa-info-circle"></i> En los días registrados como feriado, el kiosco <strong>no permitirá marcar asistencia</strong>.</div>
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Fecha</th><th>Día</th><th>Nombre del feriado</th><th style="width:110px">Acciones</th></tr></thead>
            <tbody>
            @foreach($feriados as $f)
                <tr>
                    <td data-order="{{ $f->fecha->toDateString() }}">{{ $f->fecha->format('d/m/Y') }}</td>
                    <td>{{ ucfirst($f->fecha->locale('es')->dayName) }}</td>
                    <td>{{ $f->nombre }}</td>
                    <td>
                        <a href="{{ route('feriados.edit', $f) }}" class="btn btn-sm btn-info"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('feriados.destroy', $f) }}" class="d-inline form-eliminar">
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
