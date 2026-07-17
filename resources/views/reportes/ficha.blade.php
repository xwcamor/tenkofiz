<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Asistencia — {{ $empleado->nombre_completo }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        body { margin: 30px 40px; color: #222; font-size: 12px; }
        .cabecera { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #1f4e79; padding-bottom: 12px; }
        .cabecera img { max-height: 70px; }
        .empresa h2 { margin: 0; color: #1f4e79; font-size: 18px; }
        .empresa p { margin: 2px 0; color: #555; font-size: 11px; }
        h3.titulo { text-align: center; margin: 18px 0 4px; text-transform: uppercase; color: #1f4e79; }
        p.rango { text-align: center; margin: 0 0 14px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 5px 7px; }
        th { background: #1f4e79; color: #fff; font-size: 11px; }
        .seccion { background: #e8eef5; font-weight: bold; color: #1f4e79; padding: 6px 8px; border-left: 4px solid #1f4e79; margin: 16px 0 8px; }
        .resumen td { text-align: center; font-weight: bold; font-size: 13px; }
        .firmas { display: flex; justify-content: space-around; margin-top: 70px; text-align: center; }
        .firmas div { border-top: 1px solid #333; width: 220px; padding-top: 5px; font-size: 11px; }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button { background: #1f4e79; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        @media print { .no-print { display: none; } body { margin: 10px 15px; } }
        .badge { padding: 2px 6px; border-radius: 4px; color: #fff; font-size: 10px; }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">🖨 Imprimir / Guardar como PDF</button>
</div>

<div class="cabecera">
    <div class="empresa">
        <h2>{{ $ajuste->empresa }}</h2>
        @if($ajuste->ruc)<p>RUC: {{ $ajuste->ruc }}</p>@endif
        @if($ajuste->direccion)<p>{{ $ajuste->direccion }} @if($ajuste->telefono) — Tel: {{ $ajuste->telefono }} @endif</p>@endif
    </div>
    @if($ajuste->logo)<img src="{{ asset($ajuste->logo) }}" alt="logo">@endif
</div>

<h3 class="titulo">Ficha de Asistencia del Trabajador</h3>
<p class="rango">Periodo: del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }} — Emitido: {{ now()->format('d/m/Y H:i') }}</p>

<div class="seccion">I. Datos del Trabajador</div>
<table>
    <tr>
        <th style="width:35%">Apellidos y Nombres</th><td>{{ $empleado->nombre_completo }}</td>
        <th style="width:12%">DNI</th><td>{{ $empleado->dni }}</td>
    </tr>
    <tr>
        <th>Área / Cargo</th><td>{{ $empleado->area?->nombre ?? '—' }} / {{ $empleado->cargo?->nombre ?? '—' }}</td>
        <th>Horario</th><td>{{ $empleado->horario?->nombre ?? '—' }} ({{ $empleado->horario ? substr($empleado->horario->hora_entrada,0,5).' - '.substr($empleado->horario->hora_salida,0,5) : '' }})</td>
    </tr>
    <tr>
        <th>Fecha de ingreso</th><td>{{ $empleado->fecha_ingreso?->format('d/m/Y') ?? '—' }}</td>
        <th>Estado</th><td>{{ $empleado->activo ? 'ACTIVO' : 'INACTIVO' }}</td>
    </tr>
</table>

<div class="seccion">II. Resumen del Periodo</div>
<table class="resumen">
    <tr><th>Días trabajados</th><th>Puntuales</th><th>Tardanzas</th><th>Faltas</th><th>Justificados</th><th>Horas trabajadas</th></tr>
    <tr>
        <td>{{ $resumen['dias'] }}</td>
        <td style="color:#28a745">{{ $resumen['puntuales'] }}</td>
        <td style="color:#d39e00">{{ $resumen['tardanzas'] }}</td>
        <td style="color:#dc3545">{{ $resumen['faltas'] }}</td>
        <td style="color:#17a2b8">{{ $resumen['justificados'] }}</td>
        <td>{{ $resumen['horas'] }} hrs</td>
    </tr>
</table>

<div class="seccion">III. Detalle de Asistencias</div>
<table>
    <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Método</th><th>Observación</th></tr></thead>
    <tbody>
    @forelse($asistencias as $a)
        <tr>
            <td>{{ $a->fecha->format('d/m/Y') }}</td>
            <td style="text-align:center">{{ $a->hora_entrada ?? '—' }}</td>
            <td style="text-align:center">{{ $a->hora_salida ?? '—' }}</td>
            <td style="text-align:center">{{ $a->estado }}</td>
            <td style="text-align:center">{{ $a->metodo }}</td>
            <td>{{ $a->observacion }}</td>
        </tr>
    @empty
        <tr><td colspan="6" style="text-align:center;color:#888">Sin registros en el periodo</td></tr>
    @endforelse
    </tbody>
</table>

<div class="seccion">IV. Vacaciones Aprobadas en el Periodo</div>
<table>
    <thead><tr><th>Inicio</th><th>Fin</th><th>Días</th><th>Motivo</th></tr></thead>
    <tbody>
    @forelse($vacaciones as $v)
        <tr>
            <td>{{ $v->fecha_inicio->format('d/m/Y') }}</td>
            <td>{{ $v->fecha_fin->format('d/m/Y') }}</td>
            <td style="text-align:center">{{ $v->dias }}</td>
            <td>{{ $v->motivo }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;color:#888">Sin vacaciones en el periodo</td></tr>
    @endforelse
    </tbody>
</table>

<div class="seccion">V. Justificaciones del Periodo</div>
<table>
    <thead><tr><th>Fecha</th><th>Motivo</th><th>Estado</th><th>Revisado por</th></tr></thead>
    <tbody>
    @forelse($justificaciones as $j)
        <tr>
            <td>{{ $j->fecha->format('d/m/Y') }}</td>
            <td>{{ $j->motivo }}</td>
            <td style="text-align:center">{{ $j->estado }}</td>
            <td>{{ $j->revisor?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;color:#888">Sin justificaciones en el periodo</td></tr>
    @endforelse
    </tbody>
</table>

<div class="firmas">
    <div>{{ $empleado->nombre_completo }}<br>TRABAJADOR</div>
    <div>{{ $ajuste->empresa }}<br>EMPLEADOR / RR.HH.</div>
</div>
</body>
</html>
