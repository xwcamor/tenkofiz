<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\Feriado;
use Illuminate\Http\Request;

class KioscoController extends Controller
{
    /** Umbral de distancia euclidiana: menor = más parecido (0.55 equilibra precisión y tolerancia a cambios de luz) */
    public const UMBRAL = 0.55;

    /** Minutos mínimos que deben pasar entre la entrada y la salida (evita doble marcado) */
    public const MIN_MINUTOS_PARA_SALIDA = 30;

    public function index()
    {
        return view('kiosco.index');
    }

    /** Devuelve los descriptores de todos los empleados enrolados (para el matching en el navegador) */
    public function descriptores()
    {
        $empleados = Empleado::where('activo', true)
            ->whereNotNull('descriptor_facial')
            ->get(['id', 'nombres', 'apellidos', 'descriptor_facial']);

        return response()->json($empleados->map(function ($e) {
            $data = json_decode($e->descriptor_facial, true);
            // Compatibilidad: formato antiguo = 1 vector plano; nuevo = lista de vectores
            $descriptores = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data : [$data];

            return [
                'id' => $e->id,
                'nombre' => $e->nombre_completo,
                'descriptores' => $descriptores,
            ];
        }));
    }

    /** Registra entrada o salida según corresponda */
    public function marcar(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'exists:empleados,id'],
            'distancia' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        if ($datos['distancia'] > self::UMBRAL) {
            return response()->json(['ok' => false, 'mensaje' => 'Rostro no reconocido con suficiente confianza.'], 422);
        }

        $empleado = Empleado::with('horario')->findOrFail($datos['empleado_id']);
        $hoy = now()->toDateString();
        $ahora = now()->format('H:i:s');

        // Bloqueo por feriado
        if ($feriado = Feriado::esFeriado($hoy)) {
            return response()->json([
                'ok' => false,
                'mensaje' => "Hoy es feriado ({$feriado->nombre}): no corresponde marcar asistencia.",
            ], 422);
        }

        // Bloqueo si el empleado está de vacaciones aprobadas
        if ($empleado->deVacaciones($hoy)) {
            return response()->json([
                'ok' => false,
                'mensaje' => "{$empleado->nombre_completo} se encuentra de vacaciones: no corresponde marcar asistencia.",
            ], 422);
        }

        $asistencia = Asistencia::firstOrNew(['empleado_id' => $empleado->id, 'fecha' => $hoy]);

        if (!$asistencia->exists) {
            // Primera marca del día = ENTRADA
            $estado = 'PUNTUAL';
            if ($empleado->horario) {
                $limite = \Carbon\Carbon::parse($hoy.' '.$empleado->horario->hora_entrada)
                    ->addMinutes($empleado->horario->tolerancia_min);
                if (now()->greaterThan($limite)) {
                    $estado = 'TARDANZA';
                }
            }
            $asistencia->fill([
                'hora_entrada' => $ahora,
                'estado' => $estado,
                'metodo' => 'FACIAL',
                'similitud' => $datos['distancia'],
            ])->save();

            return response()->json([
                'ok' => true,
                'tipo' => 'ENTRADA',
                'estado' => $estado,
                'empleado' => $empleado->nombre_completo,
                'hora' => now()->format('h:i a'),
            ]);
        }

        if (is_null($asistencia->hora_salida)) {
            // Validar tiempo mínimo desde la entrada (regla de negocio anti doble marcado)
            $entrada = \Carbon\Carbon::parse($hoy.' '.$asistencia->hora_entrada);
            $minutosTranscurridos = $entrada->diffInMinutes(now());

            if ($minutosTranscurridos < self::MIN_MINUTOS_PARA_SALIDA) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => "{$empleado->nombre_completo}: su ENTRADA ya fue registrada a las "
                        . $entrada->format('h:i a') . ". Podrá marcar salida en "
                        . (self::MIN_MINUTOS_PARA_SALIDA - $minutosTranscurridos) . " minuto(s).",
                ], 422);
            }

            // Segunda marca = SALIDA
            $asistencia->update(['hora_salida' => $ahora]);

            return response()->json([
                'ok' => true,
                'tipo' => 'SALIDA',
                'estado' => $asistencia->estado,
                'empleado' => $empleado->nombre_completo,
                'hora' => now()->format('h:i a'),
            ]);
        }

        return response()->json([
            'ok' => false,
            'mensaje' => "{$empleado->nombre_completo} ya registró entrada y salida hoy.",
        ], 422);
    }
}
