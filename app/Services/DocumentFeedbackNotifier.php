<?php

namespace App\Services;

use App\Mail\DocumentFeedbackForGkr;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stuurt GKR een e-mail als een klant reageert op een document of akkoord geeft.
 *
 * De regels:
 * - De mail gaat naar het gedeelde adres van GKR (CHAT_MAIL_GKR_ADDRESS), net als bij de chat.
 * - Akkoord: altijd een mail. Dat is het moment waarop GKR verder kan.
 * - Reactie: één mail per document tot GKR zelf reageert. Schrijft de klant meerdere
 *   reacties achter elkaar, dan komt er maar één mail. Na 24 uur mag er weer een komen.
 * - In de mail staat nooit de tekst van de reactie, alleen dát er een reactie is.
 * - Een mislukte mail mag de reactie of het akkoord nooit tegenhouden.
 * - Zelfde veiligheidsschakelaar als bij de andere mails (APPOINTMENT_MAIL_EVERYONE).
 *
 * Wat al gemaild is, onthouden we in de cache. Raakt die leeg (bijvoorbeeld na een deploy),
 * dan is het ergste dat er één keer een mail te veel gaat.
 */
class DocumentFeedbackNotifier
{
    // Wordt er niet gereageerd, dan mag er na zoveel uur weer een mail komen
    private const NOTIFIED_HOURS = 24;

    /**
     * Er is zojuist een reactie geplaatst op dit document.
     */
    public function commentPlaced(Document $document, User $author): void
    {
        try {
            // GKR reageert zelf: de stapel is afgehandeld, een volgende reactie mag weer een mail geven
            if ($author->isAdmin()) {
                Cache::forget($this->notifiedKey($document));

                return;
            }

            if (Cache::has($this->notifiedKey($document))) {
                return;
            }

            if ($this->send($document, $author, DocumentFeedbackForGkr::COMMENT, null)) {
                // Pas onthouden als het versturen gelukt is
                Cache::put($this->notifiedKey($document), true, now()->addHours(self::NOTIFIED_HOURS));
            }
        } catch (Throwable $e) {
            Log::error('E-mailmelding van een reactie op een document mislukt', [
                'document_id' => $document->id,
                'message'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * De klant heeft zojuist akkoord gegeven op (een versie van) dit document.
     */
    public function approved(Document $document, User $client, ?int $versionNumber): void
    {
        try {
            $this->send($document, $client, DocumentFeedbackForGkr::APPROVED, $versionNumber);
        } catch (Throwable $e) {
            Log::error('E-mailmelding van een akkoord op een document mislukt', [
                'document_id' => $document->id,
                'message'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verstuurt de mail. Geeft true terug als er echt een mail is verstuurd.
     */
    private function send(Document $document, User $client, string $kind, ?int $versionNumber): bool
    {
        // Alleen een reactie of akkoord van de klant van dít project
        if ($client->isAdmin() || (int) ($document->project?->user_id ?? 0) !== (int) $client->id) {
            return false;
        }

        $address = trim((string) config('services.chat.gkr_address'));

        // Geen (geldig) adres ingesteld: dan sturen we niets
        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (! $this->mayMail($address)) {
            return false;
        }

        Mail::to($address)->send(new DocumentFeedbackForGkr($client, $document, $kind, $versionNumber));

        return true;
    }

    private function mayMail(string $address): bool
    {
        if (config('services.appointments.mail_everyone')) {
            return true;
        }

        $testRecipient = trim((string) config('services.appointments.mail_test_recipient'));

        return $testRecipient !== '' && strcasecmp($address, $testRecipient) === 0;
    }

    private function notifiedKey(Document $document): string
    {
        return 'documents:comment-notified:' . $document->id;
    }
}