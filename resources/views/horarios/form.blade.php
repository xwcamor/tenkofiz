@extends('layouts.app')
@section('titulo', $horario->exists ? 'Editar Horario' : 'Nuevo Horario')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-clock"></i> Datos del horario</h3></div>
            <form method="POST" action="{{ $horario->exists ? route('horarios.update', $horario) : route('horarios.store') }}">
                @csrf
                @if($horario->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input name="nombre" value="{{ old('nombre', $horario->nombre) }}" class="form-control" required placeholder="Ej: Turno Mañana">
                    </div>
                    <div class="row">
                        <div class="col form-group">
                            <label>Hora de entrada</label>
                            <input type="time" name="hora_entrada" value="{{ old('hora_entrada', $horario->hora_entrada ? substr($horario->hora_entrada,0,5) : '') }}" class="form-control" required>
                        </div>
                        <div class="col form-group">
                            <label>Hora de salida</label>
                            <input type="time" name="hora_salida" value="{{ old('hora_salida', $horario->hora_salida ? substr($horario->hora_salida,0,5) : '') }}" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tolerancia de tardanza (minutos)</label>
                        <input type="number" name="tolerancia_min" value="{{ old('tolerancia_min', $horario->tolerancia_min ?? 10) }}" class="form-control" min="0" max="60" required>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="activo" value="1" class="custom-control-input" id="act" @checked(old('activo', $horario->activo ?? true))>
                        <label class="custom-control-label" for="act">Activo</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    <a href="{{ route('horarios.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
