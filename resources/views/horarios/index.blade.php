@extends('layouts.app')
@section('titulo', 'Horarios')
@section('boton-header')
    <a href="{{ route('horarios.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo horario</a>
@endsection
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-datos">
            <thead><tr><th>Nombre</th><th>Entrada</th><th>Salida</th><th>Tolerancia</th><th>Empleados</th><th style="width:110px">Acciones</th></tr></thead>
            <tbody>
            @foreach($horarios as $h)
                <tr>
                    <td>{{ $h->nombre }}</td>
                    <td>{{ substr($h->hora_entrada,0,5) }}</td>
                    <td>{{ substr($h->hora_salida,0,5) }}</td>
                    <td>{{ $h->tolerancia_min }} min</td>
                    <td><span class="badge badge-info">{{ $h->empleados_count }}</span></td>
                    <td>
                        <a href="{{ route('horarios.edit', $h) }}" class="btn btn-sm btn-info"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('horarios.destroy', $h) }}" class="d-inline form-eliminar">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
