<?php

use App\Models\Setting;
use App\Models\User;

if (!function_exists('current_company_id')) {
    /** The workspace (company) the current request operates in (null = all/none) */
    function current_company_id(): ?int
    {
        try {
            return \App\Models\Scopes\CompanyScope::currentCompanyId();
        } catch (\Throwable) {
            return null; // DB not migrated yet
        }
    }
}

if (!function_exists('current_company')) {
    /** The Company model of the current workspace (null = super console / guest without site) */
    function current_company(): ?\App\Models\Company
    {
        $id = current_company_id();
        if (!$id) {
            return null;
        }
        $key = 'app.company.'.$id;
        if (!app()->bound($key)) {
            app()->instance($key, \App\Models\Company::withTrashed()->find($id));
        }

        return app($key);
    }
}

if (!function_exists('app_setting')) {
    /** Settings row of the current company, cached per company for the request */
    function app_setting(): Setting
    {
        $companyId = current_company_id();
        $key = 'app.setting.'.($companyId ?? 'global');

        return app()->bound($key)
            ? app($key)
            : tap(Setting::forCompany($companyId), fn ($s) => app()->instance($key, $s));
    }
}

if (!function_exists('company_timezone')) {
    /** Company operational timezone. The server itself runs and stores in UTC. */
    function company_timezone(): string
    {
        try {
            return app_setting()->timezone ?: 'UTC';
        } catch (\Throwable) {
            return config('app.display_timezone', 'UTC'); // DB not migrated yet
        }
    }
}

if (!function_exists('user_timezone')) {
    /** Timezone used to display dates to the current user */
    function user_timezone(): string
    {
        return auth()->user()?->displayTimezone() ?? company_timezone();
    }
}

if (!function_exists('company_now')) {
    /** Current time expressed in the company timezone (for business rules like check-in) */
    function company_now(): \Carbon\Carbon
    {
        return now()->setTimezone(company_timezone());
    }
}

if (!function_exists('to_user_tz')) {
    /** Converts a UTC timestamp to the current user's timezone for display */
    function to_user_tz(?\Carbon\CarbonInterface $moment): ?\Carbon\CarbonInterface
    {
        return $moment?->copy()->setTimezone(user_timezone());
    }
}

if (!function_exists('current_period')) {
    /**
     * Current payroll period [start, end] according to the cut-off day in Settings.
     * Cut-off 19 means: from the 20th of one month to the 19th of the next.
     * Without a cut-off day the period is the calendar month.
     */
    function current_period(?\Carbon\CarbonInterface $reference = null): array
    {
        $today = ($reference ?? company_now())->copy()->startOfDay();
        $cutoff = null;

        try {
            $cutoff = app_setting()->cutoff_day;
        } catch (\Throwable) {
            // DB not migrated yet
        }

        if (!$cutoff) {
            return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()];
        }

        if ($today->day <= $cutoff) {
            $end = $today->copy()->day($cutoff);
            $start = $today->copy()->subMonthNoOverflow()->day($cutoff)->addDay();
        } else {
            $start = $today->copy()->day($cutoff)->addDay();
            $end = $today->copy()->addMonthNoOverflow()->day($cutoff);
        }

        return [$start, $end];
    }
}

if (!function_exists('last_closed_period')) {
    /**
     * The most recent COMPLETE payroll period [start, end] — the one that already
     * closed. This is the sensible default for reports (you review/pay a finished
     * period), whereas current_period() (still open, ending in the future) is for
     * live monitoring. Cut-off 19, viewed on Jul 21 → [Jun 20, Jul 19]. Without a
     * cut-off day it is the previous calendar month.
     */
    function last_closed_period(?\Carbon\CarbonInterface $reference = null): array
    {
        $today = ($reference ?? company_now())->copy()->startOfDay();
        $cutoff = null;

        try {
            $cutoff = app_setting()->cutoff_day;
        } catch (\Throwable) {
            // DB not migrated yet
        }

        if (!$cutoff) {
            $prev = $today->copy()->subMonthNoOverflow();

            return [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth()->startOfDay()];
        }

        // End at the most recent cut-off day that has already passed.
        if ($today->day > $cutoff) {
            $end = $today->copy()->day($cutoff);
            $start = $today->copy()->subMonthNoOverflow()->day($cutoff)->addDay();
        } else {
            $end = $today->copy()->subMonthNoOverflow()->day($cutoff);
            $start = $today->copy()->subMonthsNoOverflow(2)->day($cutoff)->addDay();
        }

        return [$start, $end];
    }
}

if (!function_exists('vendor_asset')) {
    /**
     * Local-first asset: serves the file from public/ when it exists (run
     * `npm install && npm run vendor` once) and falls back to the CDN
     * otherwise. Keeps the kiosk working without internet once vendored.
     */
    function vendor_asset(string $localPath, string $cdnUrl): string
    {
        $absolute = public_path($localPath);

        return is_file($absolute)
            ? asset($localPath).'?v='.(@filemtime($absolute) ?: 1)
            : $cdnUrl;
    }
}

if (!function_exists('safe_mail')) {
    /** Sends an email without breaking the request if SMTP fails */
    function safe_mail(?string $to, string $subject, string $body): void
    {
        if (!$to) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not send email: '.$e->getMessage());
        }
    }
}

if (!function_exists('notify_telegram')) {
    /**
     * Sends a message to the approvers' Telegram group (fire-and-forget).
     * Configure TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env; without them
     * this is a no-op, so the feature is fully optional.
     */
    function notify_telegram(string $text): void
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');
        if (!$token || !$chatId) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::timeout(5)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                ['chat_id' => $chatId, 'text' => $text]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not send Telegram message: '.$e->getMessage());
        }
    }
}

if (!function_exists('notify_module_users')) {
    /** Emails every active user whose profile grants the given module (approval notifications) */
    function notify_module_users(string $module, string $subject, string $body): void
    {
        User::inCompany()
            ->where('is_active', true)
            ->whereHas('profile', fn ($q) => $q->where('is_active', true))
            ->with('profile')
            ->get()
            ->filter(fn (User $user) => $user->hasModule($module))
            ->each(fn (User $user) => safe_mail($user->email, $subject, $body));
    }
}

if (!function_exists('device_summary')) {
    /** Human-readable "Browser — OS" summary parsed from a user-agent string */
    function device_summary(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        $os = match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '?',
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '?',
        };

        $kind = (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone'))
            ? __('mobile') : __('desktop');

        return "$browser — $os ($kind)";
    }
}
