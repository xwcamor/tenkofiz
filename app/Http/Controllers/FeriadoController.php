<?php

namespace App\Http\Controllers;

use App\Models\Feriado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeriadoController extends Controller
{
    /** Genera automáticamente los feriados nacionales de un año (fijos + Semana Santa calculada) */
    public function generar(Request $request)
    {
        $anio = (int) $request->validate(['anio' => ['required', 'integer', 'min:2020', 'max:2100']])['anio'];

        $fijos = [
            ["$anio-01-01", 'Año Nuevo'],
            ["$anio-05-01", 'Día del Trabajo'],
            ["$anio-06-07", 'Batalla de Arica y Día de la Bandera'],
            ["$anio-06-29", 'San Pedro y San Pablo'],
            ["$anio-07-23", 'Día de la Fuerza Aérea del Perú'],
            ["$anio-07-28", 'Fiestas Patrias'],
            ["$anio-07-29", 'Fiestas Patrias'],
            ["$anio-08-06", 'Batalla de Junín'],
            ["$anio-08-30", 'Santa Rosa de Lima'],
            ["$anio-10-08", 'Combate de Angamos'],
            ["$anio-11-01", 'Todos los Santos'],
            ["$anio-12-08", 'Inmaculada Concepción'],
            ["$anio-12-09", 'Batalla de Ayacucho'],
            ["$anio-12-25", 'Navidad'],
        ];

        // Semana Santa: se calcula la Pascua y se derivan Jueves y Viernes Santo
        $pascua = function_exists('easter_date')
            ? \Carbon\Carbon::createFromTimestamp(easter_date($anio))
            : \Carbon\Carbon::create($anio, 3, 21)->addDays(easter_days($anio));

        $fijos[] = [$pascua->copy()->subDays(3)->toDateString(), 'Jueves Santo'];
        $fijos[] = [$pascua->copy()->subDays(2)->toDateString(), 'Viernes Santo'];

        $creados = 0;
        foreach ($fijos as [$fecha, $nombre]) {
            $nuevo = Feriado::firstOrCreate(['fecha' => $fecha], ['nombre' => $nombre]);
            if ($nuevo->wasRecentlyCreated) {
                $creados++;
            }
        }

        return back()->with('ok', "Feriados del año {$anio} generados: {$creados} nuevos (los existentes no se duplican).");
    }

    public function index()
    {
        $feriados = Feriado::orderBy('fecha')->get();
        return view('feriados.index', compact('feriados'));
    }

    public function create()
    {
        return view('feriados.form', ['feriado' => new Feriado()]);
    }

    public function store(Request $request)
    {
        Feriado::create($this->validar($request));
        return redirect()->route('feriados.index')->with('ok', 'Feriado registrado.');
    }

    public function edit(Feriado $feriado)
    {
        return view('feriados.form', compact('feriado'));
    }

    public function update(Request $request, Feriado $feriado)
    {
        $feriado->update($this->validar($request, $feriado));
        return redirect()->route('feriados.index')->with('ok', 'Feriado actualizado.');
    }

    public function destroy(Feriado $feriado)
    {
        \App\Models\Auditoria::registrar('ELIMINAR', 'Feriados',
            "Se eliminó el feriado {$feriado->nombre} ({$feriado->fecha->format('d/m/Y')})", $feriado->toArray());
        $feriado->delete();
        return back()->with('ok', 'Feriado eliminado.');
    }

    private function validar(Request $request, ?Feriado $feriado = null): array
    {
        return $request->validate([
            'fecha' => ['required', 'date', Rule::unique('feriados')->ignore($feriado)],
            'nombre' => ['required', 'string', 'max:150'],
        ], [
            'fecha.unique' => 'Ya existe un feriado registrado en esa fecha.',
        ]);
    }
}
