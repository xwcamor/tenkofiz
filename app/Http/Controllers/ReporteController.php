<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Empleado;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    /** Reporte de horas y días trabajados por empleado en un rango de fechas */
    public function index(Request $request)
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $empleados = Empleado::with([
            'area', 'cargo', 'horario',
            'asistencias' => fn ($q) => $q->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]),
            'vacaciones' => fn ($q) => $q->where('estado', 'APROBADO'),
        ])->where('activo', true)->orderBy('apellidos')->get();

        $filas = $empleados->map(function ($e) {
            $asis = $e->asistencias;

            $minutos = 0;
            foreach ($asis as $a) {
                if ($a->hora_entrada && $a->hora_salida) {
                    $ini = \Carbon\Carbon::parse($a->fecha->toDateString().' '.$a->hora_entrada);
                    $fin = \Carbon\Carbon::parse($a->fecha->toDateString().' '.$a->hora_salida);
                    $minutos += $ini->diffInMinutes($fin);
                }
            }

            return [
                'id' => $e->id,
                'empleado' => $e->nombre_completo,
                'dni' => $e->dni,
                'area' => $e->area?->nombre ?? '—',
                'cargo' => $e->cargo?->nombre ?? '—',
                'dias_trabajados' => $asis->whereNotNull('hora_entrada')->whereIn('estado', ['PUNTUAL', 'TARDANZA'])->count(),
                'puntuales' => $asis->where('estado', 'PUNTUAL')->count(),
                'tardanzas' => $asis->where('estado', 'TARDANZA')->count(),
                'faltas' => $asis->where('estado', 'FALTA')->count(),
                'justificados' => $asis->where('estado', 'JUSTIFICADO')->count(),
                'horas_trabajadas' => sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60),
                'dias_vacaciones' => $e->vacaciones->sum('dias'),
            ];
        });

        return view('reportes.index', compact('filas', 'desde', 'hasta'));
    }

    /** Redirige al empleado logueado a su propia ficha */
    public function miFicha(Request $request)
    {
        $empleado = Empleado::where('user_id', $request->user()->id)->first();

        if (!$empleado) {
            return redirect()->route('dashboard')->with('error', 'Su usuario no está vinculado a un empleado.');
        }

        return redirect()->route('reportes.ficha', ['empleado' => $empleado->id] + $request->only(['desde', 'hasta']));
    }

    /** Ficha formal imprimible (PDF vía navegador): gestores ven cualquiera, el empleado solo la suya */
    public function ficha(Request $request, Empleado $empleado)
    {
        $user = $request->user();
        if (!$user->tienePerfil('Administrador', 'Supervisor') && $empleado->user_id !== $user->id) {
            abort(403, 'Solo puede ver su propia ficha.');
        }

        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $ajuste = Ajuste::obtener();

        $asistencias = $empleado->asistencias()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->get();

        $minutos = 0;
        foreach ($asistencias as $a) {
            if ($a->hora_entrada && $a->hora_salida) {
                $minutos += \Carbon\Carbon::parse($a->fecha->toDateString().' '.$a->hora_entrada)
                    ->diffInMinutes(\Carbon\Carbon::parse($a->fecha->toDateString().' '.$a->hora_salida));
            }
        }

        $resumen = [
            'dias' => $asistencias->whereIn('estado', ['PUNTUAL', 'TARDANZA'])->count(),
            'puntuales' => $asistencias->where('estado', 'PUNTUAL')->count(),
            'tardanzas' => $asistencias->where('estado', 'TARDANZA')->count(),
            'faltas' => $asistencias->where('estado', 'FALTA')->count(),
            'justificados' => $asistencias->where('estado', 'JUSTIFICADO')->count(),
            'horas' => sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60),
        ];

        $vacaciones = $empleado->vacaciones()
            ->where('estado', 'APROBADO')
            ->where(fn ($q) => $q->whereBetween('fecha_inicio', [$desde, $hasta])->orWhereBetween('fecha_fin', [$desde, $hasta]))
            ->get();

        $justificaciones = $empleado->justificaciones()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get();

        return view('reportes.ficha', compact('empleado', 'ajuste', 'asistencias', 'resumen', 'vacaciones', 'justificaciones', 'desde', 'hasta'));
    }
}
