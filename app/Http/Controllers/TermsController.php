<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function show(Request $request)
    {
        // Already accepted the current version: nothing to do here
        if ($request->user()->hasAcceptedTerms()) {
            return redirect()->route('dashboard');
        }

        return view('auth.terms', ['version' => User::TERMS_VERSION]);
    }

    /** Records the acceptance (timestamp + IP + version) — the legal evidence */
    public function accept(Request $request)
    {
        $request->validate(['accept' => ['accepted']], [
            'accept.accepted' => __('You must accept the terms and conditions to continue.'),
        ]);

        $request->user()->update([
            'terms_accepted_at' => now(),
            'terms_version' => User::TERMS_VERSION,
            'terms_ip' => $request->ip(),
        ]);

        AuditLog::record('UPDATE', 'Users',
            __('User :email accepted the terms and conditions (version :version)', [
                'email' => $request->user()->email,
                'version' => User::TERMS_VERSION,
            ]));

        return redirect()->route('dashboard')->with('ok', __('Thank you. Terms and conditions accepted.'));
    }
}
