@extends('layouts.app')
@section('titulo', 'Empleados')
@section('boton-header')
    <a href="{{ route('empleados.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo empleado</a>
@endsection

@push('scripts')
<script>
/** Crea el usuario del empleado con un click: pide el correo, la contraseña inicial es su DNI */
async function crearUsuario(id, nombre) {
    const { value: email } = await Swal.fire({
        title: 'Crear usuario para ' + nombre,
        input: 'email',
        inputPlaceholder: 'correo@empresa.com',
        text: 'Se creará con perfil Empleado. La contraseña inicial será su DNI.',
        showCancelButton: true,
        confirmButtonText: 'Crear usuario',
        cancelButtonText: 'Cancelar',
        validationMessage: 'Ingrese un correo válido'
    });
    if (!email) return;

    const res = await fetch(`/empleados/${id}/crear-usuario`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ email })
    });
    const data = await res.json();

    if (res.ok && data.ok) {
        await Swal.fire({
            icon: 'success',
            title: 'Usuario creado',
            html: `<b>Correo:</b> ${data.email}<br><b>Contraseña inicial:</b> ${data.password}<br><small class="text-muted">Entregue estas credenciales al empleado.</small>`
        });
        location.reload();
    } else {
        Swal.fire('Atención', data.mensaje || data.message || 'No se pudo crear el usuario.', 'warning');
    }
}
</script>
@endpush
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>DNI</th><th>Apellidos y Nombres</th><th>Área / Cargo</th><th>Horario</th><th>Usuario</th><th>Rostro</th><th style="width:150px">Acciones</th></tr></thead>
            <tbody>
            @foreach($empleados as $e)
                <tr>
                    <td>{{ $e->dni }}</td>
                    <td>{{ $e->nombre_completo }}</td>
                    <td>{{ $e->area?->nombre ?? '—' }}{{ $e->cargo ? ' / '.$e->cargo->nombre : '' }}</td>
                    <td>{{ $e->horario?->nombre ?? '—' }}</td>
                    <td>
                        @if($e->user)
                            <span class="badge badge-primary" title="{{ $e->user->email }}"><i class="fas fa-link"></i> {{ $e->user->name }}</span>
                        @else
                            <button type="button" class="btn btn-xs btn-outline-success" onclick="crearUsuario({{ $e->id }}, '{{ $e->nombres }} {{ $e->apellidos }}')" title="Crear usuario de acceso para este empleado"><i class="fas fa-user-plus"></i> Crear usuario</button>
                        @endif
                    </td>
                    <td>
                        @if($e->tieneRostro())
                            <span class="badge badge-success"><i class="fas fa-check"></i> Enrolado</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Pendiente</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('empleados.enrolar', $e) }}" class="btn btn-sm btn-success" title="Enrolar rostro"><i class="fas fa-camera"></i></a>
                        <a href="{{ route('empleados.edit', $e) }}" class="btn btn-sm btn-info" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('empleados.destroy', $e) }}" class="d-inline form-eliminar">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
