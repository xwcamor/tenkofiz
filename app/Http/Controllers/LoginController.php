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
            $user = Auth::user();

            // Commercial kill-switch: users of a suspended/deleted workspace can't sign in
            if ($user->company_id && !$user->isSuperAdmin()) {
                $company = \App\Models\Company::withTrashed()->find($user->company_id);
                if (!$company || !$company->isOperational()) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => __('This workspace is suspended. Please contact your service provider.'),
                    ])->onlyInput('email');
                }
            }

            $request->session()->regenerate();
            $this->recordLogin($request, $user);

            return redirect()->intended(route('dashboard'));
        }

        // Failed attempts are part of the security log too (who/where/what device).
        // Attribute the event to the attempted account's workspace when it exists.
        $target = \App\Models\User::withoutGlobalScopes()->where('email', $request->input('email'))->first();
        \App\Models\AuditLog::create([
            'company_id' => $target?->company_id,
            'user_id' => $target?->id,
            'action' => 'LOGIN_FAILED',
            'module' => 'Security',
            'description' => __('Failed sign-in attempt for :email', ['email' => $request->input('email')]),
            'data' => ['device' => device_summary($request->userAgent()), 'user_agent' => $request->userAgent()],
            'ip' => $request->ip(),
        ]);

        return back()->withErrors(['email' => __('Invalid credentials or inactive user.')])->onlyInput('email');
    }

    /**
     * Security log entry for a successful sign-in: IP, parsed device and location.
     * Location comes from the browser's GPS (only if the person granted the
     * permission — that is the real place); without it only the IP remains, which
     * approximates the internet provider, not the person.
     */
    private function recordLogin(Request $request, $user): void
    {
        $data = [
            'device' => device_summary($request->userAgent()),
            'user_agent' => $request->userAgent(),
        ];

        $lat = $request->input('geo_lat');
        $lng = $request->input('geo_lng');
        $where = __('location not shared (IP only, approximate)');

        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = round((float) $lat, 6);
            $lng = round((float) $lng, 6);
            $data['lat'] = $lat;
            $data['lng'] = $lng;
            $data['accuracy_m'] = is_numeric($request->input('geo_acc')) ? (int) $request->input('geo_acc') : null;
            $data['maps'] = "https://www.google.com/maps?q={$lat},{$lng}";
            $where = __('GPS :lat, :lng (±:acc m)', ['lat' => $lat, 'lng' => $lng, 'acc' => $data['accuracy_m'] ?? '?']);
        }

        \App\Models\AuditLog::record('LOGIN', 'Security',
            __(':name signed in — :device — :where', [
                'name' => $user->name,
                'device' => $data['device'],
                'where' => $where,
            ]),
            $data);
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
