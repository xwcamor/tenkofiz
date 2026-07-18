<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Commercial kill-switch: when the super-admin suspends (non-payment) or deletes a
 * workspace, its users are cut off immediately — even sessions that were already
 * open. The super-admin themself is never blocked (they must be able to manage
 * and re-activate workspaces).
 */
class EnsureCompanyOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->company_id && !$user->isSuperAdmin()) {
            $company = Company::withTrashed()->find($user->company_id);

            if (!$company || !$company->isOperational()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => __('This workspace is suspended. Please contact your service provider.'),
                ]);
            }
        }

        return $next($request);
    }
}
