<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKioskToken
{
    /**
     * Two layers of protection for the kiosk:
     *
     *  1. Device binding (strongest): once a device is paired (an admin
     *     generates a one-time code, the tablet opens /kiosk/pair with it and
     *     receives a long-lived cookie), only that device — the one carrying
     *     the cookie — can open the kiosk. A copied URL on another device is
     *     rejected, because it has no cookie. This is the closest a web kiosk
     *     gets to a locked-down app.
     *
     *  2. Kiosk token (fallback, used when no device is paired): the device
     *     must open the kiosk once with ?token=XXX; the token is then kept in
     *     the session. If neither a bound device nor a token is configured, the
     *     kiosk stays open (a warning is shown to administrators in Settings).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $setting = null;

        try {
            $setting = app_setting();
        } catch (\Throwable) {
            return $next($request); // DB not migrated yet
        }

        // Layer 1: device binding takes precedence when a device is paired
        if ($setting->kiosk_device_hash) {
            $cookie = $request->cookie('kiosk_device');

            if (!is_string($cookie) || !hash_equals($setting->kiosk_device_hash, hash('sha256', $cookie))) {
                abort(403, __('This device is not paired with the kiosk. Ask an administrator for a pairing code.'));
            }

            return $next($request); // paired device: authorized
        }

        // Layer 2: kiosk token
        if ($setting->kiosk_token) {
            $provided = $request->query('token') ?? $request->session()->get('kiosk_token');

            if (!is_string($provided) || !hash_equals($setting->kiosk_token, $provided)) {
                abort(403, __('This device is not authorized to use the kiosk. Ask an administrator for the kiosk link.'));
            }

            $request->session()->put('kiosk_token', $setting->kiosk_token);
        }

        return $next($request);
    }
}
