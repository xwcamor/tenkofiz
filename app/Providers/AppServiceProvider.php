<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
    }

    public function boot(): void
    {
        // The whole UI is Bootstrap 4 (AdminLTE): render pagination links with it
        Paginator::useBootstrapFour();

        // Password recovery email using the configured company name and locale
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            $company = __('Attendance System');
            try {
                $company = app_setting()->company_name;
            } catch (\Throwable $e) {
                // DB not migrated yet: keep the default name
            }

            return (new MailMessage)
                ->subject(__('Password recovery').' — '.$company)
                ->greeting(__('Hello!'))
                ->line(__('We received a request to reset the password of your account.'))
                ->action(__('Reset password'), $url)
                ->line(__('This link expires in 60 minutes.'))
                ->line(__('If you did not request the change, ignore this email.'))
                ->salutation(__('Kind regards,').' '.$company);
        });
    }
}
