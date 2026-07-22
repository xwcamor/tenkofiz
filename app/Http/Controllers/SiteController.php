<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $query = Site::withCount(['employees', 'kioskDevices'])->with('kioskDevices');
        [$sort, $dir] = $this->applySort($query, $request, [
            'name' => 'name', 'address' => 'address', 'timezone' => 'timezone',
            'employees' => 'employees_count', 'status' => 'is_active',
        ], 'name');
        $sites = $query->get();

        return view('sites.index', compact('sites', 'sort', 'dir'));
    }

    public function store(Request $request)
    {
        // Plan limit (SaaS): the workspace cannot exceed its contracted sites
        if (($company = current_company()) && !$company->canAddSite()) {
            return back()->withInput()->with('error',
                __('Your plan allows up to :max sites. Contact your service provider to extend it.', ['max' => $company->max_sites]));
        }

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
        // Design rule: a workspace must always keep at least one site (employees and
        // kiosks are always scoped to one). The last site is locked.
        if (Site::count() <= 1) {
            return back()->with('error', __('You must keep at least one site.'));
        }

        // A site is required on every employee, so deleting one with staff would
        // orphan them (broken kiosk scoping, blank site in reports). Block it and
        // ask to move them first — same rule as schedules.
        if ($site->employees()->exists()) {
            return back()->with('error', __('Cannot delete: there are employees in this site. Move them to another site first.'));
        }

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

    /** Revoke ONE paired tablet (the others keep working) */
    public function revokeDevice(Site $site, \App\Models\KioskDevice $device)
    {
        abort_unless($device->site_id === $site->id, 404);

        $name = $device->name;
        $device->delete();

        AuditLog::record('UPDATE', 'Sites', __('A kiosk device (:device) was revoked from site :name', ['device' => $name, 'name' => $site->name]));

        return back()->with('ok', __('Tablet ":device" revoked for :name. It can no longer open this kiosk.', ['device' => $name, 'name' => $site->name]));
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sites')->ignore($site)->where('company_id', current_company_id())],
            'address' => ['nullable', 'string', 'max:200'],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'name.unique' => __('A site with that name already exists.'),
        ]) + ['is_active' => $site ? $request->boolean('is_active') : true];
    }
}
