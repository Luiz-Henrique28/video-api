<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$request->user()->name) {

            return response()->json([
                'message' => 'Você precisa definir um nome de usuário para continuar.',
                'code' => 'USERNAME_REQUIRED', // O Front usa isso para saber o que fazer
                'action' => 'redirect_to_setup'
            ], 403);
        }
        return $next($request);
    }
}
