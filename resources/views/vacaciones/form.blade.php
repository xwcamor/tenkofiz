@extends('layouts.app')
@section('titulo', 'Solicitar Vacaciones')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-umbrella-beach"></i> Nueva solicitud</h3></div>
            <form method="POST" action="{{ route('vacaciones.store') }}">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label>Empleado</label>
                        <select name="empleado_id" class="form-control @error('empleado_id') is-invalid @enderror" required>
                            @foreach($empleados as $e)
                                <option value="{{ $e->id }}">{{ $e->nombre_completo }}</option>
                            @endforeach
                        </select>
                        @error('empleado_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="col form-group">
                            <label>Fecha de inicio</label>
                            <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}" class="form-control @error('fecha_inicio') is-invalid @enderror" required>
                            @error('fecha_inicio')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col form-group">
                            <label>Fecha de fin</label>
                            <input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" class="form-control @error('fecha_fin') is-invalid @enderror" required>
                            @error('fecha_fin')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Motivo <small class="text-muted">(opcional)</small></label>
                        <textarea name="motivo" class="form-control" rows="2">{{ old('motivo') }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Enviar solicitud</button>
                    <a href="{{ route('vacaciones.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
