<?php

namespace App\Services;

use App\Mail\NewDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stuurt de klant een e-mail als er een nieuw document of een nieuwe versie voor hem klaarstaat.
 *
 * De regels:
 * - De mail gaat alleen naar de klant van wie het project is.
 * - In de mail staan de naam van het document en het versienummer, maar nooit het
 *   bestand zelf of de inhoud. De klant opent het document na het inloggen.
 * - Een mislukte mail mag het uploaden nooit tegenhouden: we schrijven de fout in de log.
 * - Zelfde veiligheidsschakelaar als bij de afspraak- en chatmails: zolang
 *   APPOINTMENT_MAIL_EVERYONE niet op true staat, gaat er alleen mail naar het testadres.
 */
class DocumentNotifier
{
    public function documentReady(Document $document, int $versionNumber): void
    {
        try {
            $this->notify($document, $versionNumber);
        } catch (Throwable $e) {
            Log::error('E-mailmelding van een nieuw document mislukt', [
                'document_id' => $document->id,
                'message'     => $e->getMessage(),
            ]);
        }
    }

    private function notify(Document $document, int $versionNumber): void
    {
        // De klant komt altijd uit het project, nooit uit een formulier
        $client = $document->project?->user;

        if (! $client || $client->isAdmin()) {
            return;
        }

        $address = trim((string) $client->email);

        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (! $this->mayMail($address)) {
            return;
        }

        Mail::to($address)->send(new NewDocument($client, $document, $versionNumber));
    }

    private function mayMail(string $address): bool
    {
        if (config('services.appointments.mail_everyone')) {
            return true;
        }

        $testRecipient = trim((string) config('services.appointments.mail_test_recipient'));

        return $testRecipient !== '' && strcasecmp($address, $testRecipient) === 0;
    }
}