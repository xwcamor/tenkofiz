@extends('layouts.app')
@section('titulo', 'Nueva Justificación')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-file-medical"></i> Registrar justificación</h3></div>
            <form method="POST" action="{{ route('justificaciones.store') }}" enctype="multipart/form-data">
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
                    <div class="form-group">
                        <label>Fecha a justificar</label>
                        <input type="date" name="fecha" value="{{ old('fecha') }}" class="form-control @error('fecha') is-invalid @enderror" required max="{{ now()->toDateString() }}">
                        @error('fecha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Motivo</label>
                        <textarea name="motivo" class="form-control @error('motivo') is-invalid @enderror" rows="3" required placeholder="Ej: Cita médica — adjunto constancia de atención">{{ old('motivo') }}</textarea>
                        @error('motivo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Documento sustentatorio <small class="text-muted">(PDF o imagen, máx. 2MB)</small></label>
                        <input type="file" name="documento" class="form-control-file @error('documento') is-invalid @enderror" accept=".pdf,image/png,image/jpeg">
                        @error('documento')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Enviar justificación</button>
                    <a href="{{ route('justificaciones.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
