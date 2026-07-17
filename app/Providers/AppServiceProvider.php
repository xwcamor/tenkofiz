<?php

namespace App\Providers;

use App\Models\Ajuste;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/correo.php');
    }

    public function boot(): void
    {
        // Correo de recuperación de contraseña en español
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            $empresa = 'Sistema de Asistencia';
            try {
                $empresa = Ajuste::obtener()->empresa;
            } catch (\Throwable $e) {
                // BD aún no migrada: usar nombre por defecto
            }

            return (new MailMessage)
                ->subject("Recuperación de contraseña — {$empresa}")
                ->greeting('¡Hola!')
                ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line('Este enlace expira en 60 minutos.')
                ->line('Si usted no solicitó el cambio, ignore este correo.')
                ->salutation("Atentamente, {$empresa}");
        });
    }
}
