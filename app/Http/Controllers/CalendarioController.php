<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Feriado;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    /** Calendario de asistencias, feriados y vacaciones (plugin FullCalendar) */
    public function index(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->tienePerfil('Administrador', 'Supervisor');

        $empleados = $esGestor ? Empleado::where('activo', true)->orderBy('apellidos')->get() : collect();

        // El gestor puede elegir empleado; el empleado ve el suyo
        $empleado = $esGestor && $request->filled('empleado_id')
            ? Empleado::find($request->integer('empleado_id'))
            : Empleado::where('user_id', $user->id)->first();

        $eventos = [];

        if ($empleado) {
            foreach ($empleado->asistencias()->get() as $a) {
                $color = match ($a->estado) {
                    'PUNTUAL' => '#28a745',
                    'TARDANZA' => '#ffc107',
                    'JUSTIFICADO' => '#17a2b8',
                    default => '#dc3545',
                };
                $titulo = $a->hora_entrada
                    ? 'E: '.substr($a->hora_entrada, 0, 5).($a->hora_salida ? ' | S: '.substr($a->hora_salida, 0, 5) : '')
                    : $a->estado;
                $eventos[] = ['title' => $titulo, 'start' => $a->fecha->toDateString(), 'color' => $color];
            }

            foreach ($empleado->vacaciones()->where('estado', 'APROBADO')->get() as $v) {
                $eventos[] = [
                    'title' => 'Vacaciones',
                    'start' => $v->fecha_inicio->toDateString(),
                    'end' => $v->fecha_fin->addDay()->toDateString(),
                    'color' => '#6f42c1',
                ];
            }
        }

        foreach (Feriado::all() as $f) {
            $eventos[] = ['title' => '🎉 '.$f->nombre, 'start' => $f->fecha->toDateString(), 'display' => 'background', 'color' => '#f8d7da'];
        }

        return view('calendario.index', compact('eventos', 'empleado', 'empleados', 'esGestor'));
    }
}
