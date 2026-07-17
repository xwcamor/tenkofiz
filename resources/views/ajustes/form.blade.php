@extends('layouts.app')
@section('titulo', 'Ajustes de la Empresa')
@section('contenido')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-building"></i> Datos de la empresa (aparecen en reportes y fichas)</h3></div>
            <form method="POST" action="{{ route('ajustes.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>Nombre / Razón Social</label>
                        <input name="empresa" value="{{ old('empresa', $ajuste->empresa) }}" class="form-control @error('empresa') is-invalid @enderror" required>
                        @error('empresa')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>RUC</label>
                            <input name="ruc" value="{{ old('ruc', $ajuste->ruc) }}" class="form-control @error('ruc') is-invalid @enderror" maxlength="11">
                            @error('ruc')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Teléfono</label>
                            <input name="telefono" value="{{ old('telefono', $ajuste->telefono) }}" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input name="direccion" value="{{ old('direccion', $ajuste->direccion) }}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Logo <small class="text-muted">(PNG/JPG, máx. 2MB)</small></label>
                        <input type="file" name="logo" class="form-control-file @error('logo') is-invalid @enderror" accept="image/png,image/jpeg">
                        @error('logo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        @if($ajuste->logo)
                            <div class="mt-2"><img src="{{ asset($ajuste->logo) }}" alt="logo" style="max-height:80px" class="border rounded p-1 bg-white"></div>
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar ajustes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
