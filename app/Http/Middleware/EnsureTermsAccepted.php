<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    /**
     * Legal gate: an authenticated user who has not accepted the CURRENT version
     * of the terms and conditions can only see the terms screen (and log out).
     * Acceptance is recorded with timestamp, IP and version on the user row.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('terms.enforced', true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && !$user->hasAcceptedTerms() && !$request->routeIs('terms.*', 'logout', 'locale.switch')) {
            return redirect()->route('terms.show');
        }

        return $next($request);
    }
}
