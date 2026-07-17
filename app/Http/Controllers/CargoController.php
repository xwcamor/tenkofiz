<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    /** Alta rápida desde el formulario de empleados (AJAX) */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:cargos,nombre'],
        ], [
            'nombre.unique' => 'Ese cargo ya existe.',
        ]);

        $cargo = Cargo::create($datos);

        return response()->json(['id' => $cargo->id, 'nombre' => $cargo->nombre]);
    }
}
