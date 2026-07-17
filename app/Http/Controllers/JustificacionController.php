<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\Empleado;
use App\Models\Justificacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JustificacionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->tienePerfil('Administrador', 'Supervisor');

        $justificaciones = Justificacion::with(['empleado', 'revisor'])
            ->when(!$esGestor, fn ($q) => $q->whereHas('empleado', fn ($w) => $w->where('user_id', $user->id)))
            ->orderByDesc('fecha')
            ->get();

        return view('justificaciones.index', compact('justificaciones', 'esGestor'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $esGestor = $user->tienePerfil('Administrador', 'Supervisor');

        $empleados = $esGestor
            ? Empleado::where('activo', true)->orderBy('apellidos')->get()
            : Empleado::where('user_id', $user->id)->get();

        return view('justificaciones.form', compact('empleados'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'exists:empleados,id', Rule::unique('justificaciones')->where('fecha', $request->input('fecha'))],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'max:300'],
            'documento' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
        ], [
            'empleado_id.unique' => 'Ya existe una justificación para ese empleado en esa fecha.',
            'documento.mimes' => 'El documento debe ser PDF o imagen (png/jpg).',
        ]);

        $user = $request->user();
        if (!$user->tienePerfil('Administrador', 'Supervisor')) {
            $propio = Empleado::where('user_id', $user->id)->value('id');
            abort_if((int) $datos['empleado_id'] !== (int) $propio, 403);
        }

        if ($request->hasFile('documento')) {
            $dir = public_path('uploads/justificaciones');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $nombre = uniqid('just_').'.'.$request->file('documento')->getClientOriginalExtension();
            $request->file('documento')->move($dir, $nombre);
            $datos['documento'] = 'uploads/justificaciones/'.$nombre;
        }

        Justificacion::create($datos);

        return redirect()->route('justificaciones.index')->with('ok', 'Justificación registrada. Queda pendiente de revisión.');
    }

    /** El gestor acepta o rechaza; al aceptar, el día queda como JUSTIFICADO en asistencias */
    public function cambiarEstado(Request $request, Justificacion $justificacion)
    {
        $datos = $request->validate(['estado' => ['required', 'in:ACEPTADO,RECHAZADO,PENDIENTE']]);

        $justificacion->update([
            'estado' => $datos['estado'],
            'revisado_por' => $request->user()->id,
        ]);

        if ($datos['estado'] === 'ACEPTADO') {
            Asistencia::updateOrCreate(
                ['empleado_id' => $justificacion->empleado_id, 'fecha' => $justificacion->fecha->toDateString()],
                ['estado' => 'JUSTIFICADO', 'metodo' => 'MANUAL', 'observacion' => 'Justificación aprobada: '.$justificacion->motivo]
            );
        }

        Auditoria::registrar('EDITAR', 'Justificaciones',
            "Justificación de {$justificacion->empleado->nombre_completo} ({$justificacion->fecha->format('d/m/Y')}) marcada como {$datos['estado']}",
            $justificacion->toArray());

        $justificacion->load('empleado.user');
        enviarCorreoSeguro(
            $justificacion->empleado->user?->email,
            "Su justificación fue {$datos['estado']}",
            "Hola {$justificacion->empleado->nombres},\n\nSu justificación del día {$justificacion->fecha->format('d/m/Y')} ({$justificacion->motivo}) fue {$datos['estado']}.\n\nSaludos."
        );

        return back()->with('ok', "Justificación {$datos['estado']}.");
    }

    public function destroy(Justificacion $justificacion)
    {
        Auditoria::registrar('ELIMINAR', 'Justificaciones',
            "Se eliminó la justificación de {$justificacion->empleado->nombre_completo} del {$justificacion->fecha->format('d/m/Y')}",
            $justificacion->toArray());

        $justificacion->delete();
        return back()->with('ok', 'Justificación eliminada.');
    }
}
