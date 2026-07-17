@extends('layouts.app')
@section('titulo', 'Asistencias')
@section('boton-header')
    <div class="d-flex">
        <form method="POST" action="{{ route('asistencias.marcarFaltas') }}" class="form-inline mr-2">
            @csrf
            <input type="date" name="fecha" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm mr-1" required>
            <button class="btn btn-danger btn-sm" title="Marca FALTA a quienes no registraron asistencia ese día (excluye feriados, domingos, vacaciones)"><i class="fas fa-user-times"></i> Generar faltas</button>
        </form>
        <button class="btn btn-outline-primary btn-sm" data-toggle="collapse" data-target="#formManual"><i class="fas fa-plus"></i> Registro manual</button>
    </div>
@endsection
@section('contenido')

<div id="formManual" class="collapse">
    <div class="card card-warning card-outline">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-pencil-alt"></i> Registro manual (justificaciones)</h3></div>
        <form method="POST" action="{{ route('asistencias.store') }}">
            @csrf
            <div class="card-body row">
                <div class="col-md-3 form-group">
                    <label>Empleado</label>
                    <select name="empleado_id" class="form-control" required>
                        <option value="">— Seleccione —</option>
                        @foreach($empleados as $e)<option value="{{ $e->id }}">{{ $e->nombre_completo }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group"><label>Fecha</label><input type="date" name="fecha" class="form-control" required></div>
                <div class="col-md-1 form-group"><label>Entrada</label><input type="time" name="hora_entrada" class="form-control"></div>
                <div class="col-md-1 form-group"><label>Salida</label><input type="time" name="hora_salida" class="form-control"></div>
                <div class="col-md-2 form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control" required>
                        @foreach(['PUNTUAL','TARDANZA','FALTA','JUSTIFICADO'] as $est)<option>{{ $est }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group"><label>Observación</label><input name="observacion" class="form-control"></div>
            </div>
            <div class="card-footer"><button class="btn btn-warning"><i class="fas fa-save"></i> Registrar</button></div>
        </form>
    </div>
</div>

<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">Desde</label>
            <input type="date" name="desde" value="{{ $desde->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="form-control form-control-sm mr-3">
            <select name="estado" class="form-control form-control-sm mr-3">
                <option value="">Todos los estados</option>
                @foreach(['PUNTUAL','TARDANZA','FALTA','JUSTIFICADO'] as $est)
                    <option @selected(request('estado') == $est)>{{ $est }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Fecha</th><th>Empleado</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Método</th><th>Observación</th><th style="width:60px">Editar</th></tr></thead>
            <tbody>
            @foreach($asistencias as $a)
                <tr>
                    <td>{{ $a->fecha->format('d/m/Y') }}</td>
                    <td>{{ $a->empleado->nombre_completo }}</td>
                    <td>{{ $a->hora_entrada ?? '—' }}</td>
                    <td>{{ $a->hora_salida ?? '—' }}</td>
                    <td><span class="badge badge-{{ $a->estado === 'PUNTUAL' ? 'success' : ($a->estado === 'TARDANZA' ? 'warning' : ($a->estado === 'FALTA' ? 'danger' : 'info')) }}">{{ $a->estado }}</span></td>
                    <td><i class="fas fa-{{ $a->metodo === 'FACIAL' ? 'id-badge' : 'pencil-alt' }}"></i> {{ $a->metodo }}</td>
                    <td class="text-muted">{{ $a->observacion }}</td>
                    <td class="text-center">
                        <a href="{{ route('asistencias.edit', $a) }}" class="btn btn-sm btn-info" title="Editar horas/estado"><i class="fas fa-pencil-alt"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
