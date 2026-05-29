<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SitePasswordProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Controleer of de app op Railway staat (production)
        // Als je lokaal test (local), skipt hij deze beveiliging automatisch
        if (app()->environment('production')) {
            
            // 2. Haal de inloggegevens op uit de Railway instellingen (die maken we in stap 4)
            $username = env('SITE_TEST_USER', 'admin');
            $password = env('SITE_TEST_PASSWORD');

            // 3. Als er geen wachtwoord is ingesteld op Railway, blokkeer dan voor de veiligheid
            if (empty($password)) {
                abort(503, 'Website is momenteel afgeschermd.');
            }

            // 4. Toon de pop-up in de browser van de gebruiker
            if ($request->getUser() !== $username || $request->getPassword() !== $password) {
                return response('Niet toegestaan. Voer de test-gegevens in.', 401, [
                    'WWW-Authenticate' => 'Basic realm="Testomgeving"'
                ]);
            }
        }

        return $next($request);
    }
}