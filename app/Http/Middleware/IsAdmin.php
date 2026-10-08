<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Web (admin-paneel) krijgt bij geen adminrechten een redirect naar het dashboard, zoals
     * altijd. Een JSON-/API-request (de mobiele app) krijgt een 403: een redirect naar een
     * HTML-pagina is voor een API-client onbruikbaar en zou er als "gelukt" uit kunnen zien.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Geen adminrechten.'], 403);
        }

        return redirect('/dashboard')->with('error', 'Geen admin toegang.');
    }
}
