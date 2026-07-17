<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /** Users flagged with must_change_password may only access the account screen */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && !$request->routeIs('account.*', 'logout', 'locale.switch')) {
            return redirect()->route('account.edit')
                ->with('error', __('For security reasons, you must change your initial password before continuing.'));
        }

        return $next($request);
    }
}
