<?php

namespace App\Http\Controllers;

use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::with('perfil')->orderBy('name')->get();
        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $perfiles = Perfil::where('activo', true)->orderBy('nombre')->get();
        return view('usuarios.form', ['usuario' => new User(), 'perfiles' => $perfiles]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'activo' => ['boolean'],
        ]);

        $datos['activo'] = $request->boolean('activo');
        User::create($datos);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $perfiles = Perfil::where('activo', true)->orderBy('nombre')->get();
        return view('usuarios.form', compact('usuario', 'perfiles'));
    }

    public function update(Request $request, User $usuario)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($usuario)],
            'password' => ['nullable', 'string', 'min:6'],
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'activo' => ['boolean'],
        ]);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }
        $datos['activo'] = $request->boolean('activo');
        $usuario->update($datos);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puede eliminar su propio usuario.');
        }
        \App\Models\Auditoria::registrar('ELIMINAR', 'Usuarios',
            "Se eliminó el usuario {$usuario->name} ({$usuario->email})", $usuario->toArray());
        $usuario->delete();
        return back()->with('ok', 'Usuario eliminado.');
    }
}
