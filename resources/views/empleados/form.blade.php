@extends('layouts.app')
@section('titulo', $empleado->exists ? 'Editar Empleado' : 'Nuevo Empleado')
@section('contenido')
<div class="row">
    <div class="col-md-8">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user"></i> Datos del empleado</h3></div>
            <form method="POST" action="{{ $empleado->exists ? route('empleados.update', $empleado) : route('empleados.store') }}">
                @csrf
                @if($empleado->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>DNI</label>
                            <input name="dni" value="{{ old('dni', $empleado->dni) }}" class="form-control @error('dni') is-invalid @enderror" required maxlength="12" pattern="[0-9]{8,12}" title="Solo números (8 a 12 dígitos)">
                            @error('dni')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Nombres</label>
                            <input name="nombres" value="{{ old('nombres', $empleado->nombres) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Apellidos</label>
                            <input name="apellidos" value="{{ old('apellidos', $empleado->apellidos) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Área</label>
                            <div class="input-group">
                                <select name="area_id" id="selArea" class="form-control @error('area_id') is-invalid @enderror">
                                    <option value="">— Sin área —</option>
                                    @foreach($areas as $a)
                                        <option value="{{ $a->id }}" @selected(old('area_id', $empleado->area_id) == $a->id)>{{ $a->nombre }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" onclick="agregarCatalogo('{{ route('areas.store') }}', 'selArea', 'área')" title="Agregar nueva área"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Cargo</label>
                            <div class="input-group">
                                <select name="cargo_id" id="selCargo" class="form-control @error('cargo_id') is-invalid @enderror">
                                    <option value="">— Sin cargo —</option>
                                    @foreach($cargos as $c)
                                        <option value="{{ $c->id }}" @selected(old('cargo_id', $empleado->cargo_id) == $c->id)>{{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" onclick="agregarCatalogo('{{ route('cargos.store') }}', 'selCargo', 'cargo')" title="Agregar nuevo cargo"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Fecha de ingreso</label>
                            <input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', $empleado->fecha_ingreso?->toDateString()) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Horario asignado <span class="text-danger">*</span></label>
                            <select name="horario_id" class="form-control @error('horario_id') is-invalid @enderror" required>
                                <option value="">— Seleccione un horario —</option>
                                @foreach($horarios as $h)
                                    <option value="{{ $h->id }}" @selected(old('horario_id', $empleado->horario_id) == $h->id)>{{ $h->nombre }} ({{ substr($h->hora_entrada,0,5) }}–{{ substr($h->hora_salida,0,5) }})</option>
                                @endforeach
                            </select>
                            @error('horario_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Usuario del sistema <small class="text-muted">(para que vea sus asistencias)</small></label>
                            <select name="user_id" class="form-control @error('user_id') is-invalid @enderror">
                                <option value="">— Sin usuario —</option>
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}" @selected(old('user_id', $empleado->user_id) == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('user_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="activo" value="1" class="custom-control-input" id="act" @checked(old('activo', $empleado->activo ?? true))>
                        <label class="custom-control-label" for="act">Activo</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                    <a href="{{ route('empleados.index') }}" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/** Alta rápida de áreas y cargos sin salir del formulario (con SweetAlert2 + AJAX) */
async function agregarCatalogo(url, selectId, etiqueta) {
    const { value: nombre } = await Swal.fire({
        title: 'Nueva ' + etiqueta,
        input: 'text',
        inputPlaceholder: 'Nombre de la ' + etiqueta,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        inputValidator: v => !v && 'Ingrese un nombre'
    });
    if (!nombre) return;

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ nombre })
    });

    if (res.ok) {
        const data = await res.json();
        const sel = document.getElementById(selectId);
        const opt = new Option(data.nombre, data.id, true, true);
        sel.add(opt);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: etiqueta.charAt(0).toUpperCase() + etiqueta.slice(1) + ' agregada', showConfirmButton: false, timer: 2500 });
    } else {
        const err = await res.json();
        Swal.fire('Atención', err.message || 'No se pudo guardar (¿nombre duplicado?).', 'warning');
    }
}
</script>
@endpush
