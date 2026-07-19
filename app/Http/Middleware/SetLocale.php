<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['es', 'en'];

    /**
     * Language resolution chain: the user's personal choice, else the session
     * toggle, else the WORKSPACE default (Settings), else the app default.
     * The workspace default also covers its kiosks (guest + site session).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $this->workspaceLocale()
            ?? config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function workspaceLocale(): ?string
    {
        try {
            return current_company_id() ? app_setting()->locale : null;
        } catch (\Throwable) {
            return null; // DB not migrated yet
        }
    }
}
