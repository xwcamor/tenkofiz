<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    // ---------- Account screen: password + preferences (authenticated user) ----------

    public function edit()
    {
        return view('account.edit', [
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => __('The current password is not correct.'),
            'password.confirmed' => __('The confirmation does not match.'),
            'password.min' => __('The new password must be at least 8 characters.'),
            'password.different' => __('The new password must be different from the current one.'),
        ]);

        $request->user()->update([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('ok', __('Password updated successfully.'));
    }

    /** Per-user preferences: display timezone and language */
    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['nullable', 'timezone:all'],
            'locale' => ['required', 'in:es,en'],
        ]);

        $request->user()->update([
            'timezone' => $data['timezone'] ?: null,
            'locale' => $data['locale'],
        ]);

        $request->session()->put('locale', $data['locale']);

        return back()->with('ok', __('Preferences saved.'));
    }

    // ---------- Password recovery (guest) ----------

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('ok', __('We sent a recovery link to your email.'))
            : back()->withErrors(['email' => __('We could not find a user with that email.')]);
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('ok', __('Password reset. You can now sign in.'))
            : back()->withErrors(['email' => __($status)]);
    }
}
