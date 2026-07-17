<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('profile')->orderBy('name')->get();
        $profiles = Profile::where('is_active', true)->orderBy('name')->get();
        return view('users.index', compact('users', 'profiles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'profile_id' => ['required', 'exists:profiles,id'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        User::create($data);

        return redirect()->route('users.index')->with('ok', __('User created successfully.'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'password' => ['nullable', 'string', 'min:6'],
            'profile_id' => ['required', 'exists:profiles,id'],
            'is_active' => ['boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');

        // A user cannot deactivate their own account
        if ($user->id === auth()->id() && !$data['is_active']) {
            return back()->with('error', __('You cannot deactivate your own account.'))->withInput();
        }

        $user->update($data);

        return redirect()->route('users.index')->with('ok', __('User updated.'));
    }

    public function destroy(User $user)
    {
        // The logged-in user can never delete their own account
        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        AuditLog::record('DELETE', 'Users',
            __('User :name (:email) was deleted', ['name' => $user->name, 'email' => $user->email]),
            $user->toArray());
        $user->delete();

        return back()->with('ok', __('User deleted.'));
    }
}
