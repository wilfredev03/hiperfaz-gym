<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRol
{
    /**
     * Deja pasar solo a usuarios con alguno de los roles indicados: `rol:admin`.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $permitidos = array_map(fn (string $rol) => Rol::from($rol), $roles);

        abort_unless(in_array($request->user()?->rol, $permitidos, true), 403);

        return $next($request);
    }
}
