@extends('layouts.app')
@section('titulo', $feriado->exists ? 'Editar Feriado' : 'Nuevo Feriado')
@section('contenido')
<div class="row">
    <div class="col-md-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-times"></i> Datos del feriado</h3></div>
            <form method="POST" action="{{ $feriado->exists ? route('feriados.update', $feriado) : route('feriados.store') }}">
                @csrf
                @if($feriado->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="{{ old('fecha', $feriado->fecha?->toDateString()) }}" class="form-control @error('fecha') is-invalid @enderror" required>
                        @error('fecha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Nombre del feriado</label>
                        <input name="nombre" value="{{ old('nombre', $feriado->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required placeholder="Ej: Fiestas Patrias">
                        @error('nombre')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    <a href="{{ route('feriados.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
