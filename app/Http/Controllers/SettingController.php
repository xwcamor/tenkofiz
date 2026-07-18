<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    /** Generates (or rotates) the kiosk device token; old links stop working */
    public function regenerateKioskToken()
    {
        $setting = app_setting();
        $setting->update(['kiosk_token' => Str::random(48)]);

        AuditLog::record('UPDATE', 'Settings', __('The kiosk access token was regenerated'));

        return back()->with('ok', __('Kiosk token regenerated. Update the link on the authorized device.'));
    }

    /** Disables the kiosk token requirement (open kiosk) */
    public function clearKioskToken()
    {
        app_setting()->update(['kiosk_token' => null]);

        AuditLog::record('UPDATE', 'Settings', __('The kiosk access token was removed (open kiosk)'));

        return back()->with('ok', __('Kiosk token removed: the kiosk is now open to any device.'));
    }
}
