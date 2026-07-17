<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        $profiles = Profile::withCount('users')->orderBy('name')->get();
        return view('profiles.index', compact('profiles'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Profile::create($data);
        return redirect()->route('profiles.index')->with('ok', __('Profile created.'));
    }

    public function update(Request $request, Profile $profile)
    {
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
            'name' => ['required', 'string', 'max:50', Rule::unique('profiles')->ignore($profile)],
            'description' => ['nullable', 'string', 'max:200'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(Profile::MODULES))],
        ]);

        $data['permissions'] = array_values($data['permissions'] ?? []);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
