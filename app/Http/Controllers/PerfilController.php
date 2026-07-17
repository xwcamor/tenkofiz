<?php

namespace App\Http\Controllers;

use App\Models\Perfil;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerfilController extends Controller
{
    public function index()
    {
        $perfiles = Perfil::withCount('users')->orderBy('nombre')->get();
        return view('perfiles.index', compact('perfiles'));
    }

    public function create()
    {
        return view('perfiles.form', ['perfil' => new Perfil()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:perfiles,nombre'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ]);
        $datos['activo'] = $request->boolean('activo');
        Perfil::create($datos);
        return redirect()->route('perfiles.index')->with('ok', 'Perfil creado.');
    }

    public function edit(Perfil $perfil)
    {
        return view('perfiles.form', compact('perfil'));
    }

    public function update(Request $request, Perfil $perfil)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('perfiles')->ignore($perfil)],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ]);
        $datos['activo'] = $request->boolean('activo');
        $perfil->update($datos);
        return redirect()->route('perfiles.index')->with('ok', 'Perfil actualizado.');
    }

    public function destroy(Perfil $perfil)
    {
        if ($perfil->users()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay usuarios con este perfil.');
        }
        \App\Models\Auditoria::registrar('ELIMINAR', 'Perfiles',
            "Se eliminó el perfil {$perfil->nombre}", $perfil->toArray());
        $perfil->delete();
        return back()->with('ok', 'Perfil eliminado.');
    }
}
