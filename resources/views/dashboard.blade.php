@extends('layouts.app')
@section('titulo', 'Dashboard')
@section('contenido')

@if($esGestor)
{{-- ================= DASHBOARD GLOBAL (Administrador / Supervisor) ================= --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $totalEmpleados }}</h3><p>Empleados activos</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <a href="{{ route('empleados.index') }}" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $asistenciasHoy }}</h3><p>Asistencias hoy</p></div>
            <div class="icon"><i class="fas fa-calendar-check"></i></div>
            <a href="{{ route('asistencias.index') }}" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3>{{ $tardanzasHoy }}</h3><p>Tardanzas hoy</p></div>
            <div class="icon"><i class="fas fa-user-clock"></i></div>
            <span class="small-box-footer">&nbsp;</span>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $vacacionesPendientes }}</h3><p>Vacaciones pendientes</p></div>
            <div class="icon"><i class="fas fa-umbrella-beach"></i></div>
            <a href="{{ route('vacaciones.index') }}" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

@if($sinRostro > 0)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Hay <strong>{{ $sinRostro }}</strong> empleado(s) sin rostro enrolado: no podrán marcar asistencia facial.</div>
@endif

<div class="row">
    <div class="col-md-7">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line"></i> Asistencias de los últimos 7 días</h3></div>
            <div class="card-body"><canvas id="graficoSemana" height="180"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> Últimos marcados de hoy</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead><tr><th>Empleado</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse($ultimas as $a)
                        <tr>
                            <td>{{ $a->empleado->nombre_completo }}</td>
                            <td>{{ $a->hora_entrada }}</td>
                            <td>{{ $a->hora_salida ?? '—' }}</td>
                            <td><span class="badge badge-{{ $a->estado === 'PUNTUAL' ? 'success' : ($a->estado === 'TARDANZA' ? 'warning' : 'secondary') }}">{{ $a->estado }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Sin marcados hoy</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@else
{{-- ================= DASHBOARD PERSONAL (perfil Empleado) ================= --}}
@if(!$empleado)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Su usuario aún no está vinculado a un registro de empleado. Solicite al administrador que lo vincule para ver su información.</div>
@else
    <div class="callout callout-info">
        <h5><i class="fas fa-user"></i> Hola, {{ $empleado->nombres }}</h5>
        <p class="mb-0">{{ $empleado->cargo?->nombre ?? '' }} {{ $empleado->area ? '— '.$empleado->area->nombre : '' }} | Horario: {{ $empleado->horario?->nombre ?? 'sin asignar' }}</p>
    </div>

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="small-box bg-{{ $asistenciaHoy ? ($asistenciaHoy->estado === 'TARDANZA' ? 'warning' : 'success') : 'secondary' }}">
                <div class="inner">
                    <h3>{{ $asistenciaHoy?->hora_entrada ?? '—' }}</h3>
                    <p>Mi entrada de hoy {{ $asistenciaHoy ? '('.$asistenciaHoy->estado.')' : '(sin marcar)' }}</p>
                </div>
                <div class="icon"><i class="fas fa-sign-in-alt"></i></div>
                <span class="small-box-footer">Salida: {{ $asistenciaHoy?->hora_salida ?? 'pendiente' }}</span>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3>{{ $diasMes }}</h3><p>Días trabajados este mes</p></div>
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <a href="{{ route('asistencias.mias') }}" class="small-box-footer">Ver historial <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-4 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3>{{ $tardanzasMes }}</h3><p>Tardanzas este mes</p></div>
                <div class="icon"><i class="fas fa-user-clock"></i></div>
                <span class="small-box-footer">&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-list"></i> Mis últimas asistencias</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse($recientes as $a)
                            <tr>
                                <td>{{ $a->fecha->format('d/m/Y') }}</td>
                                <td>{{ $a->hora_entrada ?? '—' }}</td>
                                <td>{{ $a->hora_salida ?? '—' }}</td>
                                <td><span class="badge badge-{{ $a->estado === 'PUNTUAL' ? 'success' : ($a->estado === 'TARDANZA' ? 'warning' : 'secondary') }}">{{ $a->estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin asistencias registradas</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-umbrella-beach"></i> Mis vacaciones recientes</h3></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>Inicio</th><th>Fin</th><th>Días</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse($misVacaciones as $v)
                            <tr>
                                <td>{{ $v->fecha_inicio->format('d/m/Y') }}</td>
                                <td>{{ $v->fecha_fin->format('d/m/Y') }}</td>
                                <td>{{ $v->dias }}</td>
                                <td><span class="badge badge-{{ $v->estado === 'APROBADO' ? 'success' : ($v->estado === 'RECHAZADO' ? 'danger' : 'warning') }}">{{ $v->estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin solicitudes</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
@endif
@endsection

@push('scripts')
@if($esGestor)
<script>
new Chart(document.getElementById('graficoSemana'), {
    type: 'bar',
    data: {
        labels: @json($labels),
        datasets: [
            { label: 'Asistencias', data: @json($serieAsistencias), backgroundColor: 'rgba(0,123,255,.6)' },
            { label: 'Tardanzas', data: @json($serieTardanzas), backgroundColor: 'rgba(255,193,7,.7)' }
        ]
    },
    options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
@endif
@endpush
