<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HorarioController extends Controller
{
    public function index()
    {
        $horarios = Horario::withCount('empleados')->orderBy('nombre')->get();
        return view('horarios.index', compact('horarios'));
    }

    public function create()
    {
        return view('horarios.form', ['horario' => new Horario()]);
    }

    public function store(Request $request)
    {
        Horario::create($this->validar($request));
        return redirect()->route('horarios.index')->with('ok', 'Horario creado.');
    }

    public function edit(Horario $horario)
    {
        return view('horarios.form', compact('horario'));
    }

    public function update(Request $request, Horario $horario)
    {
        $horario->update($this->validar($request, $horario));
        return redirect()->route('horarios.index')->with('ok', 'Horario actualizado.');
    }

    public function destroy(Horario $horario)
    {
        if ($horario->empleados()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay empleados asignados.');
        }
        \App\Models\Auditoria::registrar('ELIMINAR', 'Horarios',
            "Se eliminó el horario {$horario->nombre}", $horario->toArray());
        $horario->delete();
        return back()->with('ok', 'Horario eliminado.');
    }

    private function validar(Request $request, ?Horario $horario = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('horarios')->ignore($horario)],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['required', 'date_format:H:i', 'after:hora_entrada'],
            'tolerancia_min' => ['required', 'integer', 'min:0', 'max:60'],
        ]);
        $datos['activo'] = $request->boolean('activo');
        return $datos;
    }
}
