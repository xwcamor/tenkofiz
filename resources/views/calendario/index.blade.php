@extends('layouts.app')
@section('titulo', 'Calendario de Asistencias')
@section('contenido')
@if($esGestor)
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form class="form-inline">
            <label class="mr-2">Ver calendario de:</label>
            <select name="empleado_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">— Seleccione un empleado —</option>
                @foreach($empleados as $e)
                    <option value="{{ $e->id }}" @selected($empleado?->id == $e->id)>{{ $e->nombre_completo }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>
@endif

@if(!$empleado && !$esGestor)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Su usuario no está vinculado a un empleado.</div>
@else
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> {{ $empleado?->nombre_completo ?? 'Seleccione un empleado' }}</h3>
        <div class="card-tools small">
            <span class="badge" style="background:#28a745;color:#fff">Puntual</span>
            <span class="badge" style="background:#ffc107">Tardanza</span>
            <span class="badge" style="background:#17a2b8;color:#fff">Justificado</span>
            <span class="badge" style="background:#6f42c1;color:#fff">Vacaciones</span>
            <span class="badge" style="background:#f8d7da">Feriado</span>
        </div>
    </div>
    <div class="card-body"><div id="calendario"></div></div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendario');
    if (!el) return;
    const calendario = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'es',
        height: 'auto',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
        events: @json($eventos)
    });
    calendario.render();
});
</script>
@endpush
