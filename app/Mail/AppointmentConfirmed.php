<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentAvailability;
use App\Services\OutlookInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AppointmentConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;

    // Voor wie deze mail is. Zonder ontvanger krijg je de versie voor de klant.
    protected ?User $recipient;

    /**
     * De klant en de medewerkers krijgen elk een eigen versie van deze mail:
     * - de klant: eigen naam, en knoppen om de afspraak in de eigen agenda te zetten;
     * - een medewerker: eigen naam, de naam van de klant, en de knop "Zet in Outlook"
     *   waarmee de (Teams-)uitnodiging naar de klant gaat.
     */
    public function __construct(Appointment $appointment, ?User $recipient = null)
    {
        $this->appointment = $appointment;
        $this->recipient = $recipient;
    }

    /**
     * Is dit de versie voor een medewerker van GKR?
     * De klant van deze afspraak krijgt altijd de klantversie, ook als dat account
     * later beheerder is geworden. Zo komt de Outlook-link (met e-mailadressen erin)
     * nooit bij een klant terecht.
     */
    private function forEmployee(): bool
    {
        return $this->recipient !== null
            && (bool) $this->recipient->is_admin
            && (int) $this->recipient->id !== (int) $this->appointment->user_id;
    }

    private function clientName(): string
    {
        return $this->appointment->client?->name ?? 'de klant';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forEmployee()
                ? 'Bevestigd: gesprek met ' . $this->clientName()
                : 'Bevestiging: Je gesprek is definitief ingepland',
        );
    }

    public function content(): Content
    {
        $start = AppointmentAvailability::toLocal($this->appointment->start_time);
        $end = AppointmentAvailability::toLocal($this->appointment->end_time);

        $forEmployee = $this->forEmployee();

        return new Content(
            view: 'emails.appointment_confirmed',
            with: [
                'forEmployee'   => $forEmployee,
                'recipientName' => $this->recipient?->name ?? $this->appointment->client?->name ?? 'klant',
                'clientName'    => $this->clientName(),

                // Alleen voor medewerkers. In de Outlook-link staan e-mailadressen,
                // dus die gaat nooit mee in de mail aan de klant.
                'outlookInviteUrl' => $forEmployee ? OutlookInvite::composeUrl($this->appointment, $this->recipient) : null,
                'portalUrl'        => $forEmployee ? route('admin.appointments.index') : null,

                // Ondertekende link: werkt zonder inloggen, maar is niet te raden of aan te passen.
                // 'absolute: false' + url(): de handtekening hangt niet af van http/https of
                // de domeinnaam, zodat hij ook achter de proxy van Railway geldig blijft.
                'icsUrl' => url(URL::signedRoute('appointments.ics', $this->appointment, absolute: false)),

                // Outlook Web met de tijden inclusief tijdzone (bijv. 2026-10-20T09:00:00+02:00)
                'outlookUrl' => 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
                    'path'    => '/calendar/action/compose',
                    'subject' => $this->appointment->title,
                    'startdt' => $start->toIso8601String(),
                    'enddt'   => $end->toIso8601String(),
                    'body'    => $this->appointment->description ?? 'Gesprek via GKR Klantportaal',
                ]),
            ],
        );
    }
}