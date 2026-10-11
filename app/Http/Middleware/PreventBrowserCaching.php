<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zegt tegen de browser: bewaar pagina's achter de inlog niet.
 *
 * Zonder dit bewaart de browser elke pagina die je bekijkt, om de terugknop snel
 * te maken. Na uitloggen kan de volgende persoon op dezelfde computer dan met de
 * terugknop zien wat er op het scherm stond (klantnamen, berichten, cijfers).
 *
 * Met "no-store" gooit de browser de pagina weg zodra je hem verlaat. De terugknop
 * vraagt de pagina dan opnieuw op bij de server, en die beslist opnieuw wie er is
 * ingelogd en wat diegene mag zien.
 */
class PreventBrowserCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Alleen als er iemand is ingelogd. De inlogpagina en andere openbare
        // pagina's bevatten geen gegevens en mogen gewoon bewaard worden.
        if ($request->user()) {
            // no-store: niet bewaren. private: ook niet in een gedeelde tussenopslag.
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}