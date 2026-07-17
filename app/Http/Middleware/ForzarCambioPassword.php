<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForzarCambioPassword
{
    /** Si el usuario tiene la marca debe_cambiar_password, solo puede acceder al formulario de cambio */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->debe_cambiar_password && !$request->routeIs('cuenta.*', 'logout')) {
            return redirect()->route('cuenta.password')
                ->with('error', 'Por seguridad, debe cambiar su contraseña inicial antes de continuar.');
        }

        return $next($request);
    }
}
