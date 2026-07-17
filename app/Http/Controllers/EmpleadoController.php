<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Horario;
use App\Models\Auditoria;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpleadoController extends Controller
{
    public function index(Request $request)
    {
        $empleados = Empleado::with(['horario', 'user', 'area', 'cargo'])
            ->orderBy('apellidos')
            ->get();

        return view('empleados.index', compact('empleados'));
    }

    public function create()
    {
        return view('empleados.form', [
            'empleado' => new Empleado(),
            'horarios' => Horario::where('activo', true)->get(),
            'areas' => Area::where('activo', true)->orderBy('nombre')->get(),
            'cargos' => Cargo::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::whereDoesntHave('empleado')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Empleado::create($this->validar($request));
        return redirect()->route('empleados.index')->with('ok', 'Empleado registrado. Ahora puede enrolar su rostro.');
    }

    public function edit(Empleado $empleado)
    {
        return view('empleados.form', [
            'empleado' => $empleado,
            'horarios' => Horario::where('activo', true)->get(),
            'areas' => Area::where('activo', true)->orderBy('nombre')->get(),
            'cargos' => Cargo::where('activo', true)->orderBy('nombre')->get(),
            'usuarios' => User::whereDoesntHave('empleado')->orWhere('id', $empleado->user_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Empleado $empleado)
    {
        $empleado->update($this->validar($request, $empleado));
        return redirect()->route('empleados.index')->with('ok', 'Empleado actualizado.');
    }

    /** Crea un usuario con perfil Empleado y lo vincula (contraseña inicial: su DNI) */
    public function crearUsuario(Request $request, Empleado $empleado)
    {
        if ($empleado->user_id) {
            return response()->json(['ok' => false, 'mensaje' => 'Este empleado ya tiene un usuario vinculado.'], 422);
        }

        $datos = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
        ], [
            'email.unique' => 'Ese correo ya está registrado en otro usuario.',
        ]);

        $perfilEmpleado = Perfil::firstOrCreate(['nombre' => 'Empleado'], ['descripcion' => 'Consulta sus asistencias y solicita vacaciones']);

        $user = User::create([
            'name' => trim($empleado->nombres.' '.$empleado->apellidos),
            'email' => $datos['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($empleado->dni),
            'perfil_id' => $perfilEmpleado->id,
            'debe_cambiar_password' => true,
        ]);

        enviarCorreoSeguro(
            $user->email,
            'Sus credenciales de acceso al Sistema de Asistencia',
            "Hola {$empleado->nombres},\n\nSe creó su cuenta de acceso:\nCorreo: {$user->email}\nContraseña inicial: {$empleado->dni}\n\nAl ingresar por primera vez, el sistema le pedirá cambiarla.\n\nSaludos."
        );

        $empleado->update(['user_id' => $user->id]);

        Auditoria::registrar('CREAR', 'Usuarios', "Se creó el usuario {$user->email} vinculado al empleado {$empleado->nombre_completo}");

        return response()->json([
            'ok' => true,
            'email' => $user->email,
            'password' => $empleado->dni,
        ]);
    }

    public function destroy(Empleado $empleado)
    {
        Auditoria::registrar('ELIMINAR', 'Empleados',
            "Se eliminó al empleado {$empleado->nombre_completo} (DNI {$empleado->dni})", $empleado->toArray());
        $empleado->delete();
        return back()->with('ok', 'Empleado eliminado.');
    }

    /** Pantalla de captura del rostro con la cámara */
    public function enrolar(Empleado $empleado)
    {
        return view('empleados.enrolar', compact('empleado'));
    }

    /** Recibe VARIOS descriptores faciales (3 muestras de 128 valores) para mayor precisión.
     *  Tolerante: también acepta el formato antiguo de una sola muestra ('descriptor'). */
    public function guardarDescriptor(Request $request, Empleado $empleado)
    {
        // Compatibilidad con el formato antiguo (una sola muestra)
        if ($request->has('descriptor') && !$request->has('descriptores')) {
            $request->merge(['descriptores' => [$request->input('descriptor')]]);
        }

        $datos = $request->validate([
            'descriptores' => ['required', 'array', 'min:1', 'max:5'],
            'descriptores.*' => ['required', 'array', 'size:128'],
            'descriptores.*.*' => ['numeric'],
        ]);

        $empleado->update(['descriptor_facial' => json_encode($datos['descriptores'])]);
        $empleado->refresh();

        // Confirmación real contra la base de datos (no solo respuesta optimista)
        if (empty($empleado->descriptor_facial)) {
            return response()->json(['ok' => false, 'message' => 'No se pudo persistir el descriptor en la base de datos.'], 500);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Rostro enrolado con '.count($datos['descriptores']).' muestras (verificado en BD).']);
    }

    private function validar(Request $request, ?Empleado $empleado = null): array
    {
        return $request->validate([
            'dni' => ['required', 'digits_between:8,12', Rule::unique('empleados')->ignore($empleado)],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'cargo_id' => ['nullable', 'exists:cargos,id'],
            'fecha_ingreso' => ['nullable', 'date'],
            'horario_id' => ['required', 'exists:horarios,id'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('empleados')->ignore($empleado)],
        ], [
            'dni.unique' => 'Ese DNI ya está registrado en otro empleado.',
            'horario_id.required' => 'Debe asignar un horario al empleado (es necesario para calcular tardanzas).',
            'user_id.unique' => 'Ese usuario ya está vinculado a otro empleado.',
        ]) + ['activo' => $request->boolean('activo')];
    }
}
