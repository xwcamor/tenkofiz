<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    /** Automatically generates the national holidays of a year (fixed + computed Easter week) */
    public function generate(Request $request)
    {
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];

        $fixed = [
            ["$year-01-01", 'Año Nuevo'],
            ["$year-05-01", 'Día del Trabajo'],
            ["$year-06-07", 'Batalla de Arica y Día de la Bandera'],
            ["$year-06-29", 'San Pedro y San Pablo'],
            ["$year-07-23", 'Día de la Fuerza Aérea del Perú'],
            ["$year-07-28", 'Fiestas Patrias'],
            ["$year-07-29", 'Fiestas Patrias'],
            ["$year-08-06", 'Batalla de Junín'],
            ["$year-08-30", 'Santa Rosa de Lima'],
            ["$year-10-08", 'Combate de Angamos'],
            ["$year-11-01", 'Todos los Santos'],
            ["$year-12-08", 'Inmaculada Concepción'],
            ["$year-12-09", 'Batalla de Ayacucho'],
            ["$year-12-25", 'Navidad'],
        ];

        // Easter week: compute Easter Sunday, then derive Holy Thursday and Good Friday
        $easter = function_exists('easter_date')
            ? \Carbon\Carbon::createFromTimestamp(easter_date($year))
            : \Carbon\Carbon::create($year, 3, 21)->addDays(easter_days($year));

        $fixed[] = [$easter->copy()->subDays(3)->toDateString(), 'Jueves Santo'];
        $fixed[] = [$easter->copy()->subDays(2)->toDateString(), 'Viernes Santo'];

        $created = 0;
        foreach ($fixed as [$date, $name]) {
            $holiday = Holiday::firstOrCreate(['date' => $date], ['name' => $name]);
            if ($holiday->wasRecentlyCreated) {
                $created++;
            }
        }

        return back()->with('ok', __('Holidays for :year generated: :count new (existing ones are not duplicated).', ['year' => $year, 'count' => $created]));
    }

    public function index()
    {
        $holidays = Holiday::orderBy('date')->get();
        return view('holidays.index', compact('holidays'));
    }

    public function store(Request $request)
    {
        Holiday::create($this->validated($request));
        return redirect()->route('holidays.index')->with('ok', __('Holiday registered.'));
    }

    public function update(Request $request, Holiday $holiday)
    {
        $holiday->update($this->validated($request, $holiday));
        return redirect()->route('holidays.index')->with('ok', __('Holiday updated.'));
    }

    public function destroy(Holiday $holiday)
    {
        AuditLog::record('DELETE', 'Holidays',
            __('Holiday :name (:date) was deleted', ['name' => $holiday->name, 'date' => $holiday->date->format('d/m/Y')]),
            $holiday->toArray());
        $holiday->delete();
        return back()->with('ok', __('Holiday deleted.'));
    }

    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays')->ignore($holiday)],
            'name' => ['required', 'string', 'max:150'],
        ], [
            'date.unique' => __('A holiday is already registered on that date.'),
        ]);
    }
}
