<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CuentaController extends Controller
{
    // ---------- Cambio de contraseña (usuario autenticado) ----------

    public function editarPassword()
    {
        return view('cuenta.password');
    }

    public function actualizarPassword(Request $request)
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:password_actual'],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.different' => 'La nueva contraseña debe ser distinta a la actual.',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->input('password')),
            'debe_cambiar_password' => false,
        ]);

        return redirect()->route('dashboard')->with('ok', 'Contraseña actualizada correctamente.');
    }

    // ---------- Recuperación de contraseña (invitado) ----------

    public function olvido()
    {
        return view('auth.forgot');
    }

    public function enviarEnlace(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $estado = Password::sendResetLink($request->only('email'));

        return $estado === Password::RESET_LINK_SENT
            ? back()->with('ok', 'Le enviamos un enlace de recuperación a su correo.')
            : back()->withErrors(['email' => 'No encontramos un usuario con ese correo.']);
    }

    public function formRestablecer(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function restablecer(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'debe_cambiar_password' => false,
                ])->save();
            }
        );

        return $estado === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('ok', 'Contraseña restablecida. Ya puede iniciar sesión.')
            : back()->withErrors(['email' => __($estado)]);
    }
}
