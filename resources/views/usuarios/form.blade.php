@extends('layouts.app')
@section('titulo', $usuario->exists ? 'Editar Usuario' : 'Nuevo Usuario')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user-cog"></i> Datos del usuario</h3></div>
            <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
                @csrf
                @if($usuario->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre completo</label>
                        <input name="name" value="{{ old('name', $usuario->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <input type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Contraseña @if($usuario->exists)<small class="text-muted">(vacío = no cambiar)</small>@endif</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $usuario->exists ? '' : 'required' }}>
                        @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Perfil</label>
                        <select name="perfil_id" class="form-control @error('perfil_id') is-invalid @enderror" required>
                            <option value="">— Seleccione —</option>
                            @foreach($perfiles as $p)
                                <option value="{{ $p->id }}" @selected(old('perfil_id', $usuario->perfil_id) == $p->id)>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        @error('perfil_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="activo" value="1" class="custom-control-input" id="act" @checked(old('activo', $usuario->activo ?? true))>
                        <label class="custom-control-label" for="act">Activo</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    <a href="{{ route('usuarios.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
