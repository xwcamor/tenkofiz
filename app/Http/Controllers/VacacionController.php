<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Vacacion;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->tienePerfil('Administrador', 'Supervisor');

        $vacaciones = Vacacion::with(['empleado', 'aprobador'])
            ->when(!$esGestor, function ($q) use ($user) {
                $q->whereHas('empleado', fn ($w) => $w->where('user_id', $user->id));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->orderByDesc('created_at')
            ->get()
            ;

        return view('vacaciones.index', compact('vacaciones', 'esGestor'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->tienePerfil('Administrador', 'Supervisor');

        $empleados = $esGestor
            ? Empleado::where('activo', true)->orderBy('apellidos')->get()
            : Empleado::where('user_id', $user->id)->get();

        return view('vacaciones.form', compact('empleados', 'esGestor'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['nullable', 'string', 'max:300'],
        ]);

        $user = $request->user();
        if (!$user->tienePerfil('Administrador', 'Supervisor')) {
            $propio = Empleado::where('user_id', $user->id)->value('id');
            abort_if((int) $datos['empleado_id'] !== (int) $propio, 403);
        }

        $datos['dias'] = \Carbon\Carbon::parse($datos['fecha_inicio'])
            ->diffInDays(\Carbon\Carbon::parse($datos['fecha_fin'])) + 1;

        Vacacion::create($datos);

        return redirect()->route('vacaciones.index')->with('ok', 'Solicitud de vacaciones registrada.');
    }

    public function cambiarEstado(Request $request, Vacacion $vacacion)
    {
        $datos = $request->validate(['estado' => ['required', 'in:APROBADO,RECHAZADO,PENDIENTE']]);

        $vacacion->update([
            'estado' => $datos['estado'],
            'aprobado_por' => $request->user()->id,
        ]);

        $vacacion->load('empleado.user');
        enviarCorreoSeguro(
            $vacacion->empleado->user?->email,
            "Su solicitud de vacaciones fue {$datos['estado']}",
            "Hola {$vacacion->empleado->nombres},\n\nSu solicitud de vacaciones del {$vacacion->fecha_inicio->format('d/m/Y')} al {$vacacion->fecha_fin->format('d/m/Y')} ({$vacacion->dias} días) fue {$datos['estado']}.\n\nSaludos."
        );

        return back()->with('ok', "Solicitud {$datos['estado']}.");
    }
}
