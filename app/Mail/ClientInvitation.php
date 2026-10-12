<?php

namespace App\Mail;

use App\Http\Controllers\InvitationController;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Welkomstmail voor een nieuwe klant, met een link om zelf een wachtwoord te kiezen.
 * In deze mail staat nooit een wachtwoord.
 */
class ClientInvitation extends Mailable
{
    public function __construct(public User $client, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Je account voor het GKR Klantportaal');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client_invitation',
            with: [
                'name'      => $this->client->name,
                'email'     => $this->client->email,
                'url'       => $this->url,
                'validDays' => InvitationController::VALID_DAYS,
            ],
        );
    }
}