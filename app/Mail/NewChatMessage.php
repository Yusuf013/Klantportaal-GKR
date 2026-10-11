<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Melding dat er een nieuw chatbericht in het portaal staat.
 *
 * Bewust zonder de tekst van het bericht: een mailbox is minder goed beveiligd dan het
 * portaal. De ontvanger leest het bericht na het inloggen.
 *
 * Er zijn twee versies:
 * - voor de klant: "een nieuw bericht van GKR";
 * - voor GKR: "een nieuw bericht van (naam klant)", met een knop naar dat gesprek.
 */
class NewChatMessage extends Mailable
{
    public function __construct(private User $client, private bool $forGkr)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forGkr
                ? 'Nieuw bericht van ' . $this->client->name . ' in het klantportaal'
                : 'Nieuw bericht van GKR in het klantportaal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_chat_message',
            with: [
                'forGkr'     => $this->forGkr,
                'clientName' => $this->client->name,
                // GKR komt meteen in het gesprek van deze klant; de klant in het eigen gesprek
                'chatUrl'    => $this->forGkr
                    ? route('chat.index', ['client_id' => $this->client->id])
                    : route('chat.index'),
            ],
        );
    }
}