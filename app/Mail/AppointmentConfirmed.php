<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Services\AppointmentAvailability;
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

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bevestiging: Je gesprek is definitief ingepland',
        );
    }

    public function content(): Content
    {
        $start = AppointmentAvailability::toLocal($this->appointment->start_time);
        $end = AppointmentAvailability::toLocal($this->appointment->end_time);

        return new Content(
            view: 'emails.appointment_confirmed',
            with: [
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