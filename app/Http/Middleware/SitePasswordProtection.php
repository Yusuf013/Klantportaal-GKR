<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SitePasswordProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.site_password')) {
            return $next($request);
        }

        if ($request->session()->get('site_password_passed') === true) {
            return $next($request);
        }

        if ($request->is(
            'site-password',
            'health',
            'up',
            'favicon.ico',
            'build/*',
            'assets/*',
            'css/*',
            'js/*',
            'images/*',
            'storage/*'
        )) {
            return $next($request);
        }

        return redirect()->route('site-password.show');
    }
}