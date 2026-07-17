@extends('layouts.app')
@section('titulo', 'Cambiar mi Contraseña')
@section('contenido')
<div class="row">
    <div class="col-md-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-key"></i> Cambiar contraseña</h3></div>
            <form method="POST" action="{{ route('cuenta.password.update') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    @if(auth()->user()->debe_cambiar_password)
                        <div class="alert alert-warning py-2"><i class="fas fa-shield-alt"></i> Por seguridad, debe cambiar su contraseña inicial para continuar usando el sistema.</div>
                    @endif
                    <div class="form-group">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" class="form-control @error('password_actual') is-invalid @enderror" required>
                        @error('password_actual')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Nueva contraseña <small class="text-muted">(mínimo 8 caracteres)</small></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
