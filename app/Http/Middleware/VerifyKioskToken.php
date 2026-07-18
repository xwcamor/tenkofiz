<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKioskToken
{
    /**
     * Per-site protection for the kiosk. Each site (tablet) has its own token and
     * its own paired device, so one site's link never opens another's kiosk.
     *
     *  1. Device binding (strongest): once a site's device is paired (an admin
     *     generates a one-time code on the Sites screen, the tablet opens
     *     /kiosk/pair with it and receives a long-lived cookie), only that device
     *     — carrying the cookie — can open that site's kiosk.
     *
     *  2. Kiosk token (fallback, used when no device is paired): the device must
     *     open the kiosk once with ?token=XXX; it is then kept in the session.
     *     If a site has neither a paired device nor a token, its kiosk stays open.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $site = $this->resolveSite($request);

        // No site context (e.g. several sites and no ?site given): behave as an
        // open kiosk — the screen itself will ask for a site link.
        if (!$site) {
            return $next($request);
        }

        // Commercial kill-switch: a suspended/deleted workspace stops marking too
        $company = $site->company_id
            ? \App\Models\Company::withTrashed()->find($site->company_id)
            : null;
        if ($company && !$company->isOperational()) {
            abort(403, __('This workspace is suspended. Please contact your service provider.'));
        }

        // Layer 1: device binding takes precedence when a device is paired
        if ($site->kiosk_device_hash) {
            $cookie = $request->cookie('kiosk_device');

            if (!is_string($cookie) || !hash_equals($site->kiosk_device_hash, hash('sha256', $cookie))) {
                abort(403, __('This device is not paired with the kiosk. Ask an administrator for a pairing code.'));
            }

            return $next($request); // paired device: authorized
        }

        // Layer 2: kiosk token (kept in the session per site)
        if ($site->kiosk_token) {
            $sessionKey = 'kiosk_token_'.$site->id;
            $provided = $request->query('token') ?? $request->session()->get($sessionKey);

            if (!is_string($provided) || !hash_equals($site->kiosk_token, $provided)) {
                abort(403, __('This device is not authorized to use the kiosk. Ask an administrator for the kiosk link.'));
            }

            $request->session()->put($sessionKey, $site->kiosk_token);
        }

        return $next($request);
    }

    /**
     * Resolves the site this kiosk request targets: the ?site query first (the
     * initial link), then the remembered session site (AJAX calls), then — for a
     * single-site company — the only site, so plain /kiosk keeps working.
     */
    private function resolveSite(Request $request): ?Site
    {
        try {
            $siteId = $request->query('site') ?: $request->session()->get('kiosk_site');

            if ($siteId) {
                return Site::find($siteId);
            }

            $active = Site::where('is_active', true)->get();

            return $active->count() === 1 ? $active->first() : null;
        } catch (\Throwable) {
            return null; // DB not migrated yet
        }
    }
}
