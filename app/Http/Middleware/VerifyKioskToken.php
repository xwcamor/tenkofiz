<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKioskToken
{
    /**
     * Restricts the kiosk to authorized devices. When a kiosk token is set in
     * the system settings, the device must open the kiosk once with ?token=XXX;
     * the token is then remembered in the session for subsequent requests.
     * If no token is configured, the kiosk stays open (with a warning shown
     * to administrators in the settings screen).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = null;

        try {
            $expected = app_setting()->kiosk_token;
        } catch (\Throwable) {
            // DB not migrated yet: let the request through
        }

        if ($expected) {
            $provided = $request->query('token') ?? $request->session()->get('kiosk_token');

            if (!is_string($provided) || !hash_equals($expected, $provided)) {
                abort(403, __('This device is not authorized to use the kiosk. Ask an administrator for the kiosk link.'));
            }

            $request->session()->put('kiosk_token', $expected);
        }

        return $next($request);
    }
}
