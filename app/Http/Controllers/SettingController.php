<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings.form', [
            'setting' => app_setting(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request)
    {
        $setting = app_setting();

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'digits:11'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'timezone:all'],
            'country' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\HolidayTemplate::COUNTRIES))],
            'locale' => ['required', \Illuminate\Validation\Rule::in(\App\Http\Middleware\SetLocale::SUPPORTED)],
            'cutoff_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'early_check_in_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'early_departure_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'kiosk_enroll_pin' => ['nullable', 'digits_between:4,8'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        $data['kiosk_enroll_pin'] = $data['kiosk_enroll_pin'] ?? null;
        $data['cutoff_day'] = $data['cutoff_day'] ?? null;
        $data['early_check_in_minutes'] = $data['early_check_in_minutes'] ?? 0;
        $data['early_departure_minutes'] = $data['early_departure_minutes'] ?? 0;
        $data['clamp_worked_hours'] = $request->boolean('clamp_worked_hours');
        $data['kiosk_fast_mode'] = $request->boolean('kiosk_fast_mode');
        $data['kiosk_liveness'] = $request->boolean('kiosk_liveness');
        // Recognition calibration (threshold + verify seconds) is core: it is
        // edited only by the super-admin from the workspace console, never here.

        if ($request->hasFile('logo')) {
            $dir = public_path('uploads');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = 'logo.'.$request->file('logo')->getClientOriginalExtension();
            $request->file('logo')->move($dir, $name);
            $data['logo'] = 'uploads/'.$name;
        }

        $setting->update($data);

        return back()->with('ok', __('Company settings saved.'));
    }
}
