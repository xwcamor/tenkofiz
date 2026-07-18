<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function index()
    {
        $sites = Site::withCount('employees')->orderBy('name')->get();

        return view('sites.index', compact('sites'));
    }

    public function store(Request $request)
    {
        Site::create($this->validated($request));

        return redirect()->route('sites.index')->with('ok', __('Site registered.'));
    }

    public function update(Request $request, Site $site)
    {
        $site->update($this->validated($request, $site));

        return redirect()->route('sites.index')->with('ok', __('Site updated.'));
    }

    public function destroy(Site $site)
    {
        AuditLog::record('DELETE', 'Sites',
            __('Site :name was deleted', ['name' => $site->name]),
            $site->toArray());
        $site->delete();

        return back()->with('ok', __('Site deleted. Its employees keep their records but lose the site assignment.'));
    }

    /** Generate (or rotate) this site's kiosk access token; old links stop working */
    public function regenerateToken(Site $site)
    {
        $site->regenerateKioskToken();

        AuditLog::record('UPDATE', 'Sites', __('The kiosk token for site :name was regenerated', ['name' => $site->name]));

        return back()->with('ok', __('Kiosk token regenerated for :name. Update the link on that site\'s tablet.', ['name' => $site->name]));
    }

    /** Remove this site's kiosk token (open kiosk for the site) */
    public function clearToken(Site $site)
    {
        $site->update(['kiosk_token' => null]);

        AuditLog::record('UPDATE', 'Sites', __('The kiosk token for site :name was removed', ['name' => $site->name]));

        return back()->with('ok', __('Kiosk token removed for :name: its kiosk is now open to any device.', ['name' => $site->name]));
    }

    /** Generate a one-time device pairing code (valid 15 min) for this site */
    public function generatePairCode(Site $site)
    {
        $code = strtoupper(Str::random(3).Str::padLeft((string) random_int(0, 999), 3, '0'));

        $site->update([
            'kiosk_pair_code' => $code,
            'kiosk_pair_expires_at' => now()->addMinutes(15),
        ]);

        AuditLog::record('UPDATE', 'Sites', __('A kiosk device pairing code was generated for site :name', ['name' => $site->name]));

        return back()
            ->with('ok', __('Pairing code generated for :name. On that site\'s tablet, open the pairing page and enter it within 15 minutes.', ['name' => $site->name]))
            ->with('pair_code', $code)
            ->with('pair_site', $site->id);
    }

    /** Unpair this site's device so a new tablet can be bound */
    public function unpairDevice(Site $site)
    {
        $site->update(['kiosk_device_hash' => null, 'kiosk_pair_code' => null, 'kiosk_pair_expires_at' => null]);

        AuditLog::record('UPDATE', 'Sites', __('The kiosk device for site :name was unpaired', ['name' => $site->name]));

        return back()->with('ok', __('Device unpaired for :name. Generate a new pairing code to bind another tablet.', ['name' => $site->name]));
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sites')->ignore($site)],
            'address' => ['nullable', 'string', 'max:200'],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'name.unique' => __('A site with that name already exists.'),
        ]) + ['is_active' => $site ? $request->boolean('is_active') : true];
    }
}
