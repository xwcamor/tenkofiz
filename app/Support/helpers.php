<?php

use App\Models\Setting;
use App\Models\User;

if (!function_exists('app_setting')) {
    /** Cached-per-request singleton of the system settings row */
    function app_setting(): Setting
    {
        return app()->bound('app.setting')
            ? app('app.setting')
            : tap(Setting::instance(), fn ($s) => app()->instance('app.setting', $s));
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

if (!function_exists('notify_module_users')) {
    /** Emails every active user whose profile grants the given module (approval notifications) */
    function notify_module_users(string $module, string $subject, string $body): void
    {
        User::where('is_active', true)
            ->whereHas('profile', fn ($q) => $q->where('is_active', true))
            ->with('profile')
            ->get()
            ->filter(fn (User $user) => $user->hasModule($module))
            ->each(fn (User $user) => safe_mail($user->email, $subject, $body));
    }
}
