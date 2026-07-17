<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModule
{
    /**
     * Route usage: middleware('module:users') or middleware('module:vacations_manage,justifications_manage')
     * Access is granted when the user's profile has ANY of the listed modules.
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyModule(...$modules)) {
            abort(403, __('You do not have permission to access this module.'));
        }

        return $next($request);
    }
}
