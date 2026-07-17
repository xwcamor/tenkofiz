<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => __('Invalid credentials or inactive user.')])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /** Language switcher (works for guests via session and for users via their profile) */
    public function switchLocale(Request $request)
    {
        $data = $request->validate(['locale' => ['required', 'in:es,en']]);

        $request->session()->put('locale', $data['locale']);
        $request->user()?->update(['locale' => $data['locale']]);

        return back();
    }
}
