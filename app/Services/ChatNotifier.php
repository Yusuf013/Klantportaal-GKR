<?php

namespace App\Services;

use App\Mail\NewChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stuurt een e-mail als er een nieuw chatbericht is, zonder dat het spam wordt.
 *
 * De regels:
 * 1. Eén mail per stapel ongelezen berichten. Komen er meer berichten bij terwijl de
 *    ander nog niet heeft gelezen, dan komt er geen mail meer. Pas nadat de ander het
 *    gesprek heeft geopend, kan een volgend bericht weer een mail geven.
 * 2. Geen mail als de ander op dat moment in het portaal zit: die ziet de rode teller al.
 * 3. In de mail staat nooit de tekst van het bericht, alleen dát er een bericht is.
 *
 * Een klant krijgt de mail op het eigen adres. Schrijft een klant, dan gaat de mail naar
 * één gedeeld adres van GKR (CHAT_MAIL_GKR_ADDRESS), niet naar elke medewerker apart.
 *
 * Dit onthouden we in de cache (kort geheugen). Raakt dat geheugen leeg, bijvoorbeeld na
 * een deploy, dan is het ergste dat er één keer een mail te veel gaat.
 */
class ChatNotifier
{
    // Zo lang telt iemand als "in het portaal" na het laatste teken van leven (in seconden).
    // De pagina's melden zich elke 5 tot 20 seconden, dus een minuut is ruim genoeg.
    private const PRESENT_SECONDS = 60;

    // Wordt er helemaal niet gelezen, dan mag er na zoveel uur weer een mail komen
    private const NOTIFIED_HOURS = 24;

    /**
     * Deze gebruiker heeft het portaal nu open (een pagina meldde zich zojuist).
     */
    public function markPresent(User $user): void
    {
        Cache::put($this->presenceKey($user->isAdmin(), (int) $user->id), true, self::PRESENT_SECONDS);
    }

    /**
     * Deze gebruiker heeft het gesprek van deze klant gelezen.
     * Daarmee is de stapel ongelezen berichten weg: een volgend bericht mag weer een mail geven.
     */
    public function conversationRead(User $reader, int $clientId): void
    {
        Cache::forget($this->notifiedKey($reader->isAdmin(), $clientId));
    }

    /**
     * Er is zojuist een bericht verstuurd in het gesprek van deze klant.
     * Een mislukte mail mag het versturen van het chatbericht nooit tegenhouden.
     */
    public function messageSent(User $sender, int $clientId): void
    {
        try {
            $this->notify($sender, $clientId);
        } catch (Throwable $e) {
            Log::error('E-mailmelding van een chatbericht mislukt', [
                'client_id' => $clientId,
                'message'   => $e->getMessage(),
            ]);
        }
    }

    private function notify(User $sender, int $clientId): void
    {
        // Schrijft een klant, dan is de mail voor GKR. Schrijft een medewerker, dan voor de klant.
        $forGkr = ! $sender->isAdmin();

        // De klant van wie dit gesprek is. Een klant kan alleen in het eigen gesprek schrijven.
        $client = $forGkr ? $sender : User::find($clientId);

        if (! $client || $client->isAdmin() || (int) $client->id !== $clientId) {
            return;
        }

        $address = $forGkr
            ? trim((string) config('services.chat.gkr_address'))
            : trim((string) $client->email);

        // Geen (geldig) adres ingesteld: dan sturen we niets
        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (! $this->mayMail($address)) {
            return;
        }

        // Regel 2: de ontvanger zit nu in het portaal en ziet de rode teller
        if (Cache::has($this->presenceKey($forGkr, $clientId))) {
            return;
        }

        // Regel 1: over deze stapel ongelezen berichten is al een mail gestuurd
        $notifiedKey = $this->notifiedKey($forGkr, $clientId);

        if (Cache::has($notifiedKey)) {
            return;
        }

        Mail::to($address)->send(new NewChatMessage($client, $forGkr));

        // Pas onthouden als het versturen gelukt is: mislukt het, dan proberen we het
        // bij het volgende bericht opnieuw
        Cache::put($notifiedKey, true, now()->addHours(self::NOTIFIED_HOURS));
    }

    /**
     * Zelfde veiligheidsschakelaar als bij de afspraakmails: zolang
     * APPOINTMENT_MAIL_EVERYONE niet op true staat, gaat er alleen mail naar het testadres.
     */
    private function mayMail(string $address): bool
    {
        if (config('services.appointments.mail_everyone')) {
            return true;
        }

        $testRecipient = trim((string) config('services.appointments.mail_test_recipient'));

        return $testRecipient !== '' && strcasecmp($address, $testRecipient) === 0;
    }

    /**
     * GKR leest als team: zit er één medewerker in het portaal, dan is "GKR" aanwezig.
     * Een klant is alleen zelf aanwezig.
     */
    private function presenceKey(bool $gkr, int $userId): string
    {
        return $gkr ? 'chat:present:gkr' : "chat:present:client:{$userId}";
    }

    /**
     * Per gesprek en per kant: is er al een mail gestuurd over de ongelezen berichten?
     */
    private function notifiedKey(bool $gkr, int $clientId): string
    {
        return 'chat:notified:' . ($gkr ? 'gkr' : 'client') . ":{$clientId}";
    }
}