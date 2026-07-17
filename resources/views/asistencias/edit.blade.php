@extends('layouts.app')
@section('titulo', 'Editar Asistencia')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-pencil-alt"></i> {{ $asistencia->empleado->nombre_completo }} — {{ $asistencia->fecha->format('d/m/Y') }}</h3></div>
            <form method="POST" action="{{ route('asistencias.update', $asistencia) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="alert alert-warning py-2"><i class="fas fa-shield-alt"></i> Esta edición quedará registrada en la <strong>auditoría</strong> del sistema.</div>
                    <div class="row">
                        <div class="col form-group">
                            <label>Hora de entrada</label>
                            <input type="time" name="hora_entrada" value="{{ old('hora_entrada', $asistencia->hora_entrada ? substr($asistencia->hora_entrada,0,5) : '') }}" class="form-control @error('hora_entrada') is-invalid @enderror">
                            @error('hora_entrada')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col form-group">
                            <label>Hora de salida</label>
                            <input type="time" name="hora_salida" value="{{ old('hora_salida', $asistencia->hora_salida ? substr($asistencia->hora_salida,0,5) : '') }}" class="form-control @error('hora_salida') is-invalid @enderror">
                            @error('hora_salida')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" class="form-control" required>
                            @foreach(['PUNTUAL','TARDANZA','FALTA','JUSTIFICADO'] as $est)
                                <option @selected(old('estado', $asistencia->estado) == $est)>{{ $est }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Observación</label>
                        <input name="observacion" value="{{ old('observacion', $asistencia->observacion) }}" class="form-control" placeholder="Motivo de la corrección">
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-warning"><i class="fas fa-save"></i> Guardar cambios</button>
                    <a href="{{ route('asistencias.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
