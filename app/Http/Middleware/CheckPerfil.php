<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPerfil
{
    /**
     * Uso en rutas: middleware('perfil:Administrador,Supervisor')
     */
    public function handle(Request $request, Closure $next, string ...$perfiles): Response
    {
        $user = $request->user();

        if (!$user || !$user->tienePerfil(...$perfiles)) {
            abort(403, 'No tiene permisos para acceder a este módulo.');
        }

        return $next($request);
    }
}
