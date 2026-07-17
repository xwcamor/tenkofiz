<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /** Alta rápida desde el formulario de empleados (AJAX) */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:areas,nombre'],
        ], [
            'nombre.unique' => 'Esa área ya existe.',
        ]);

        $area = Area::create($datos);

        return response()->json(['id' => $area->id, 'nombre' => $area->nombre]);
    }
}
