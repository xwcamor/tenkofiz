@extends('layouts.app')
@section('titulo', $perfil->exists ? 'Editar Perfil' : 'Nuevo Perfil')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-shield-alt"></i> Datos del perfil</h3></div>
            <form method="POST" action="{{ $perfil->exists ? route('perfiles.update', $perfil) : route('perfiles.store') }}">
                @csrf
                @if($perfil->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre del perfil</label>
                        <input name="nombre" value="{{ old('nombre', $perfil->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required>
                        @error('nombre')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2">{{ old('descripcion', $perfil->descripcion) }}</textarea>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="activo" value="1" class="custom-control-input" id="act" @checked(old('activo', $perfil->activo ?? true))>
                        <label class="custom-control-label" for="act">Activo</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    <a href="{{ route('perfiles.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
