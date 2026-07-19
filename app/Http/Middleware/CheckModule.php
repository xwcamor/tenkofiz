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
            // A super-admin outside a workspace is guided (not just blocked): they
            // must enter a workspace first so every action lands in ONE company.
            if ($user?->isSuperAdmin() && !session('acting_company_id')) {
                return redirect()->route('admin.companies.index')
                    ->with('error', __('Enter a workspace first: as super-admin you manage company data from inside the workspace it belongs to.'));
            }

            abort(403, __('You do not have permission to access this module.'));
        }

        return $next($request);
    }
}
