<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\HolidayTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index()
    {
        $country = request('country') ?: (app_setting()->country ?: 'PE');
        if (!array_key_exists($country, HolidayTemplate::COUNTRIES)) {
            $country = 'PE';
        }

        return view('holidays.index', [
            'holidays' => Holiday::orderBy('date')->get(),
            'countries' => HolidayTemplate::COUNTRIES,
            'country' => $country,
            'templates' => HolidayTemplate::where('country', $country)
                ->orderByRaw('COALESCE(month, 0)')->orderByRaw('COALESCE(day, 0)')->orderBy('name')->get(),
        ]);
    }

    /**
     * Turns a country's recurring templates into concrete holidays for a year.
     * No more hardcoded list: whatever the company customizes in the templates is
     * exactly what gets generated (so Chile generates Chilean holidays, etc.).
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'country' => ['required', Rule::in(array_keys(HolidayTemplate::COUNTRIES))],
        ]);

        $year = (int) $data['year'];
        $templates = HolidayTemplate::where('country', $data['country'])->get();

        if ($templates->isEmpty()) {
            return back()->with('error', __('That country has no holiday templates yet. Add them below or restore the defaults.'));
        }

        $created = 0;
        foreach ($templates as $template) {
            $date = $template->dateForYear($year);
            if (!$date) {
                continue;
            }
            $holiday = Holiday::firstOrCreate(['date' => $date], ['name' => $template->name]);
            if ($holiday->wasRecentlyCreated) {
                $created++;
            }
        }

        AuditLog::record('CREATE', 'Holidays',
            __('Holidays generated for :year (:country): :count new', ['year' => $year, 'country' => $data['country'], 'count' => $created]));

        return back()->with('ok', __('Holidays for :year generated: :count new (existing ones are not duplicated).', ['year' => $year, 'count' => $created]));
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

    // ---------- Recurring templates (per country) ----------

    public function storeTemplate(Request $request)
    {
        HolidayTemplate::create($this->validatedTemplate($request));

        return redirect()->route('holidays.index', ['country' => $request->input('country')])
            ->with('ok', __('Recurring holiday added to the template.'));
    }

    public function updateTemplate(Request $request, HolidayTemplate $template)
    {
        $template->update($this->validatedTemplate($request));

        return redirect()->route('holidays.index', ['country' => $template->country])
            ->with('ok', __('Recurring holiday updated.'));
    }

    public function destroyTemplate(HolidayTemplate $template)
    {
        $country = $template->country;
        $template->delete();

        return redirect()->route('holidays.index', ['country' => $country])
            ->with('ok', __('Recurring holiday removed from the template.'));
    }

    /** Restores a country's built-in default templates (adds the missing ones) */
    public function restoreTemplates(Request $request)
    {
        $country = $request->validate(['country' => ['required', Rule::in(array_keys(HolidayTemplate::COUNTRIES))]])['country'];

        $added = 0;
        foreach (HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
            $template = HolidayTemplate::firstOrCreate(
                ['country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]
            );
            if ($template->wasRecentlyCreated) {
                $added++;
            }
        }

        return redirect()->route('holidays.index', ['country' => $country])
            ->with('ok', __('Default templates restored: :count added.', ['count' => $added]));
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

    private function validatedTemplate(Request $request): array
    {
        // A template is either a fixed date (month + day) or Easter-relative (offset).
        $data = $request->validate([
            'country' => ['required', Rule::in(array_keys(HolidayTemplate::COUNTRIES))],
            'kind' => ['required', Rule::in(['fixed', 'easter'])],
            'name' => ['required', 'string', 'max:150'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'easter_offset' => ['nullable', 'integer', 'min:-60', 'max:60'],
        ]);

        if ($data['kind'] === 'easter') {
            return [
                'country' => $data['country'],
                'name' => $data['name'],
                'easter_offset' => $data['easter_offset'] ?? 0,
                'month' => null,
                'day' => null,
            ];
        }

        $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        return [
            'country' => $data['country'],
            'name' => $data['name'],
            'month' => $data['month'],
            'day' => $data['day'],
            'easter_offset' => null,
        ];
    }
}
