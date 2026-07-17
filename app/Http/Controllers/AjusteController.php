<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use Illuminate\Http\Request;

class AjusteController extends Controller
{
    public function edit()
    {
        return view('ajustes.form', ['ajuste' => Ajuste::obtener()]);
    }

    public function update(Request $request)
    {
        $ajuste = Ajuste::obtener();

        $datos = $request->validate([
            'empresa' => ['required', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $dir = public_path('uploads');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $nombre = 'logo.'.$request->file('logo')->getClientOriginalExtension();
            $request->file('logo')->move($dir, $nombre);
            $datos['logo'] = 'uploads/'.$nombre;
        }

        $ajuste->update($datos);

        return back()->with('ok', 'Ajustes de la empresa guardados.');
    }
}
