<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Melding aan de klant: er staat een nieuw document (of een nieuwe versie) voor je klaar.
 *
 * Bewust zonder bijlage en zonder inhoud: een mailbox is minder goed beveiligd dan het
 * portaal. De klant opent het document na het inloggen.
 */
class NewDocument extends Mailable
{
    public function __construct(
        private User $client,
        private Document $document,
        private int $versionNumber,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->versionNumber > 1
                ? 'Nieuwe versie van een document in het klantportaal'
                : 'Nieuw document van GKR in het klantportaal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_document',
            with: [
                'clientName'    => $this->client->name,
                'documentName'  => $this->document->name,
                'versionNumber' => $this->versionNumber,
                'documentUrl'   => route('documents.show', $this->document),
            ],
        );
    }
}