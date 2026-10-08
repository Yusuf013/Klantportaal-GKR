<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OutlookCalendar;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * Koppelt een medewerker aan zijn gepubliceerde Outlook-agenda (alleen vrij/bezet).
 *
 * VEILIGHEID: de link werkt als een sleutel. Daarom:
 * - gebruiken we hier bewust NIET $request->validate(). Bij een fout zet Laravel
 *   dan het ingevulde veld in de sessie (om het formulier opnieuw te vullen),
 *   en dan zou de link onversleuteld in de sessie staan;
 * - staat de link nooit in een foutmelding of succesmelding;
 * - sturen we de opgeslagen link nooit terug naar de browser.
 */
class OutlookCalendarController extends Controller
{
    private const FIELD = 'outlook_ics_url';

    public function update(Request $request, User $user, OutlookCalendar $outlook)
    {
        // Alleen medewerkers hebben een agenda in het portaal, klanten niet
        abort_unless($user->is_admin, 404);

        // Knop "Ontkoppelen"
        if ($request->boolean('remove')) {
            $user->outlook_ics_url = null;
            $user->save();

            return back()->with('success', "Outlook-agenda ontkoppeld bij {$user->name}.");
        }

        $input = $request->input(self::FIELD);
        $url = is_string($input) ? trim($input) : '';

        if ($url === '') {
            return $this->refuse('Plak eerst de ICS-koppeling uit Outlook.');
        }

        if (strlen($url) > 2000) {
            return $this->refuse('Deze link is te lang voor een Outlook-agendalink.');
        }

        // Outlook geeft twee koppelingen. De HTML-koppeling is een webpagina, geen agendabestand.
        if (str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.html')) {
            return $this->refuse('Dit is de HTML-koppeling. Gebruik de ICS-koppeling: die eindigt op .ics.');
        }

        // Eerst proberen: is het een adres van Microsoft, en krijgen we echt een agenda terug?
        try {
            $outlook->verify($url);
        } catch (InvalidArgumentException|RuntimeException $e) {
            // De meldingen van OutlookCalendar bevatten nooit de link
            return $this->refuse($e->getMessage());
        }

        $user->outlook_ics_url = $url;
        $user->save();

        return back()->with('success', "Outlook-agenda gekoppeld aan {$user->name}.");
    }

    /**
     * Terug naar de pagina met een foutmelding, zonder het ingevulde veld te bewaren.
     */
    private function refuse(string $message)
    {
        return back()->withErrors([self::FIELD => $message]);
    }
}