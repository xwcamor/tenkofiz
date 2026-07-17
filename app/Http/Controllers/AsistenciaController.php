<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Empleado;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $asistencias = Asistencia::with('empleado')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($request->filled('empleado_id'), fn ($q) => $q->where('empleado_id', $request->integer('empleado_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('fecha')
            ->get()
            ;

        $empleados = Empleado::where('activo', true)->orderBy('apellidos')->get();

        return view('asistencias.index', compact('asistencias', 'empleados', 'desde', 'hasta'));
    }

    /** Registro manual (ej. justificaciones) por Supervisor/Administrador */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'fecha' => ['required', 'date'],
            'hora_entrada' => ['nullable', 'date_format:H:i'],
            'hora_salida' => ['nullable', 'date_format:H:i', 'after:hora_entrada'],
            'estado' => ['required', 'in:PUNTUAL,TARDANZA,FALTA,JUSTIFICADO'],
            'observacion' => ['nullable', 'string', 'max:200'],
        ]);

        $datos['metodo'] = 'MANUAL';

        Asistencia::updateOrCreate(
            ['empleado_id' => $datos['empleado_id'], 'fecha' => $datos['fecha']],
            $datos
        );

        return back()->with('ok', 'Asistencia registrada manualmente.');
    }

    /** Genera las faltas de una fecha con un click (Admin / Supervisor) */
    public function marcarFaltas(Request $request)
    {
        $fecha = $request->validate(['fecha' => ['required', 'date', 'before_or_equal:today']])['fecha'];

        $creadas = Asistencia::marcarFaltas($fecha);

        Auditoria::registrar('CREAR', 'Asistencias', "Generación automática de faltas del {$fecha}: {$creadas} registro(s)");

        return back()->with('ok', "Faltas del {$fecha}: se generaron {$creadas} registro(s). (Se excluyen feriados, domingos, vacaciones y justificados)");
    }

    /** Edición de un registro de asistencia (Admin / Supervisor) */
    public function edit(Asistencia $asistencia)
    {
        $asistencia->load('empleado');
        return view('asistencias.edit', compact('asistencia'));
    }

    public function update(Request $request, Asistencia $asistencia)
    {
        $datos = $request->validate([
            'hora_entrada' => ['nullable', 'date_format:H:i'],
            'hora_salida' => ['nullable', 'date_format:H:i', 'after:hora_entrada'],
            'estado' => ['required', 'in:PUNTUAL,TARDANZA,FALTA,JUSTIFICADO'],
            'observacion' => ['nullable', 'string', 'max:200'],
        ]);

        $antes = $asistencia->toArray();
        $asistencia->update($datos + ['metodo' => 'MANUAL']);

        Auditoria::registrar('EDITAR', 'Asistencias',
            "Se editó la asistencia de {$asistencia->empleado->nombre_completo} del {$asistencia->fecha->format('d/m/Y')}",
            ['antes' => $antes, 'despues' => $asistencia->fresh()->toArray()]);

        return redirect()->route('asistencias.index')->with('ok', 'Asistencia actualizada (queda registrada en auditoría).');
    }

    /** Vista del propio empleado */
    public function misAsistencias(Request $request)
    {
        $empleado = $request->user()->empleado;

        $asistencias = $empleado
            ? $empleado->asistencias()->orderByDesc('fecha')->get()
            : collect();

        return view('asistencias.mias', compact('asistencias', 'empleado'));
    }
}
