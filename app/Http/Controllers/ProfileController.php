<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    public function index(Request $request)
    {
        $query = Profile::withCount('users');
        [$sort, $dir] = $this->applySort($query, $request, [
            'name' => 'name', 'users' => 'users_count', 'status' => 'is_active',
        ], 'name');
        $profiles = $query->get();
        return view('profiles.index', compact('profiles', 'sort', 'dir'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Profile::create($data);
        return redirect()->route('profiles.index')->with('ok', __('Profile created.'));
    }

    public function update(Request $request, Profile $profile)
    {
        // Design rule: base system profiles (Administrator/Supervisor/Employee) are
        // global read-only templates. They are NOT editable — customize by creating
        // your own profile instead. This is the whole point of them being "global".
        if ($profile->is_system) {
            return back()->with('error', __('Base system profiles cannot be edited. Create a custom profile to define your own permissions.'));
        }

        $data = $this->validated($request, $profile);

        // Safety net: don't let an admin lock themself out of profile management
        if (auth()->user()->profile_id === $profile->id && !in_array('profiles', $data['permissions'], true)) {
            return back()->with('error', __('You cannot remove the Profiles permission from your own profile.'))->withInput();
        }

        $profile->update($data);
        return redirect()->route('profiles.index')->with('ok', __('Profile updated.'));
    }

    public function destroy(Profile $profile)
    {
        if ($profile->is_system) {
            return back()->with('error', __('This is a base system profile and cannot be deleted.'));
        }
        if ($profile->users()->exists()) {
            return back()->with('error', __('Cannot delete: there are users with this profile.'));
        }
        AuditLog::record('DELETE', 'Profiles',
            __('Profile :name was deleted', ['name' => $profile->name]), $profile->toArray());
        $profile->delete();
        return back()->with('ok', __('Profile deleted.'));
    }

    private function validated(Request $request, ?Profile $profile = null): array
    {
        $data = $request->validate([
            // Name is unique WITHIN the company (each workspace has its own roles)
            'name' => ['required', 'string', 'max:50', Rule::unique('profiles')->where('company_id', current_company_id())->ignore($profile)],
            'description' => ['nullable', 'string', 'max:200'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(Profile::MODULES))],
        ]);

        $data['permissions'] = array_values($data['permissions'] ?? []);
        // New records are always active; the toggle only appears when editing
        $data['is_active'] = $profile ? $request->boolean('is_active') : true;

        // Base system profiles are protected: their name is fixed, they cannot be
        // deactivated, and the Administrator always keeps every module (so no one
        // can lock the workspace out of its own settings).
        if ($profile && $profile->is_system) {
            $data['name'] = $profile->name;
            $data['is_active'] = true;
            if ($profile->isAdministratorRole()) {
                $data['permissions'] = array_keys(Profile::MODULES);
            }
        }

        return $data;
    }
}
