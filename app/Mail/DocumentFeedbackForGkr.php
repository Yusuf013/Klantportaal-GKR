<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Melding aan GKR: een klant heeft gereageerd op een document, of akkoord gegeven.
 *
 * Bewust zonder de tekst van de reactie: een mailbox is minder goed beveiligd dan het
 * portaal. GKR leest de reactie na het inloggen.
 */
class DocumentFeedbackForGkr extends Mailable
{
    public const COMMENT = 'comment';
    public const APPROVED = 'approved';

    public function __construct(
        private User $client,
        private Document $document,
        private string $kind,
        private ?int $versionNumber,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === self::APPROVED
                ? 'Akkoord van ' . $this->client->name . ' op een document'
                : 'Nieuwe reactie van ' . $this->client->name . ' op een document',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document_feedback',
            with: [
                'approved'      => $this->kind === self::APPROVED,
                'clientName'    => $this->client->name,
                'documentName'  => $this->document->name,
                'versionNumber' => $this->versionNumber,
                'documentUrl'   => route('admin.documents.show', $this->document),
            ],
        );
    }
}