@extends('layouts.app')
@section('titulo', 'Reporte de Horas y Días Trabajados')
@section('contenido')
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">Desde</label>
            <input type="date" name="desde" value="{{ $desde->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="form-control form-control-sm mr-3">
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Generar</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover tabla-reporte">
            <thead>
                <tr>
                    <th>Empleado</th><th>DNI</th><th>Área</th><th>Cargo</th>
                    <th>Días trab.</th><th>Puntuales</th><th>Tardanzas</th><th>Faltas</th><th>Justif.</th>
                    <th>Horas trabajadas</th><th>Días vacaciones</th><th>Ficha</th>
                </tr>
            </thead>
            <tbody>
            @foreach($filas as $f)
                <tr>
                    <td>{{ $f['empleado'] }}</td>
                    <td>{{ $f['dni'] }}</td>
                    <td>{{ $f['area'] }}</td>
                    <td>{{ $f['cargo'] }}</td>
                    <td class="text-center">{{ $f['dias_trabajados'] }}</td>
                    <td class="text-center text-success font-weight-bold">{{ $f['puntuales'] }}</td>
                    <td class="text-center text-warning font-weight-bold">{{ $f['tardanzas'] }}</td>
                    <td class="text-center text-danger font-weight-bold">{{ $f['faltas'] }}</td>
                    <td class="text-center">{{ $f['justificados'] }}</td>
                    <td class="text-center">{{ $f['horas_trabajadas'] }}</td>
                    <td class="text-center">{{ $f['dias_vacaciones'] }}</td>
                    <td class="text-center">
                        <a href="{{ route('reportes.ficha', $f['id']) }}?desde={{ $desde->toDateString() }}&hasta={{ $hasta->toDateString() }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Ficha formal imprimible"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-muted mt-2"><i class="fas fa-info-circle"></i> Las horas trabajadas se calculan como la suma de (hora salida − hora entrada) de cada día con marcado completo. Use los botones para exportar a Excel o imprimir.</p>
    </div>
</div>
@endsection
