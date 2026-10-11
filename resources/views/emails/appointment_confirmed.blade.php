<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gesprek Bevestigd - GKR</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #ffffff;">
    
    <h2 style="color: #011936; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
        Beste {{ $recipientName }},
    </h2>
    @if($forEmployee)
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">Het gesprek met {{ $clientName }} is definitief en staat in de kalender van het portaal.</p>
    @else
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">Het geplande gesprek binnen het GKR Klantportaal is definitief goedgekeurd en toegevoegd aan de agenda.</p>
    @endif
    
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; margin: 24px 0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
        <h3 style="margin-top: 0; margin-bottom: 16px; color: #011936; font-size: 16px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
            {{ $appointment->title }}
        </h3>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            @if($forEmployee)
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; width: 140px; font-weight: 600; vertical-align: top;">Klant:</td>
                    <td style="padding: 8px 0; color: #011936; font-weight: 700;">{{ $clientName }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px 0; color: #6b7280; width: 140px; font-weight: 600; vertical-align: top;">Type gesprek:</td>
                <td style="padding: 8px 0; color: #011936; font-weight: 700;">{{ \App\Services\AppointmentAvailability::typeLabel($appointment->type) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 600; vertical-align: top;">Datum:</td>
                <td style="padding: 8px 0; color: #111827; font-weight: 500;">
                    {{ \Carbon\Carbon::parse($appointment->start_time)->translatedFormat('l d F Y') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 600; vertical-align: top;">Tijdstip:</td>
                <td style="padding: 8px 0; color: #111827; font-weight: 500;">
                    {{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($appointment->end_time)->format('H:i') }} uur
                </td>
            </tr>
            
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 600; vertical-align: top;">GKR Medewerker(s):</td>
                <td style="padding: 8px 0; color: #111827; font-weight: 500;">
                    @if($appointment->attendees && $appointment->attendees->count() > 0)
                        {{ $appointment->attendees->pluck('name')->join(', ', ' en ') }}
                    @else
                        Geen specifieke medewerker gekoppeld
                    @endif
                </td>
            </tr>
        </table>

        {{-- De Teams-link maakt Outlook zelf aan; die komt in een aparte uitnodiging van de medewerker --}}
        @if($appointment->type === 'online')
            @if($forEmployee)
                <p style="margin: 16px 0 0 0; font-size: 13px; color: #4b5563;">De klant verwacht nog een Teams-uitnodiging. Die verstuur je zelf met de knop hieronder.</p>
            @else
                <p style="margin: 16px 0 0 0; font-size: 13px; color: #4b5563;">Voor dit online gesprek ontvang je apart een Teams-uitnodiging van GKR. Daarin staat de link om deel te nemen.</p>
            @endif
        @endif

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
            @if($forEmployee)
                {{-- Versie voor de medewerker: Outlook zet de afspraak in de agenda en verstuurt de uitnodiging --}}
                <p style="margin: 0 0 12px 0; font-size: 13px; color: #6b7280; font-weight: 600;">Zet de afspraak in Outlook:</p>

                <a href="{{ $outlookInviteUrl }}" target="_blank" style="background-color: #011936; color: #ffffff; text-decoration: none; padding: 9px 16px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block;">
                    Zet in Outlook
                </a>

                <p style="margin: 12px 0 0 0; font-size: 13px; color: #4b5563;">Outlook opent met de afspraak al ingevuld. Klik daar op Verzenden: de afspraak staat dan in je agenda en de klant krijgt een uitnodiging{{ $appointment->type === 'online' ? ' met een Teams-link' : '' }}.</p>

                @if($appointment->attendees && $appointment->attendees->count() > 1)
                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #4b5563;">Er zijn meer collega's bij dit gesprek. Spreek af wie de uitnodiging verstuurt, zodat de klant er maar een krijgt.</p>
                @endif

                <p style="margin: 8px 0 0 0; font-size: 13px; color: #6b7280;">Dezelfde knop staat ook in het portaal: open de <a href="{{ $portalUrl }}" style="color: #011936; font-weight: 700;">kalender</a> en klik op de afspraak.</p>
            @else
            <p style="margin: 0 0 12px 0; font-size: 13px; color: #6b7280; font-weight: 600;">Afspraak toevoegen aan je agenda:</p>
            
            {{-- Beide knoppen gebruiken hetzelfde ondertekende agendabestand (.ics) --}}
            <a href="{{ $icsUrl }}" style="background-color: #ffffff; color: #011936; text-decoration: none; padding: 8px 14px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block; margin-right: 6px; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);">
                 Apple Agenda
            </a>
            
            <a href="{{ $icsUrl }}" style="background-color: #ffffff; color: #011936; text-decoration: none; padding: 8px 14px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block; margin-right: 6px; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);">
                ✉ Microsoft Outlook (App)
            </a>
            
            <a href="{{ $outlookUrl }}" target="_blank" style="background-color: #ffffff; color: #011936; text-decoration: none; padding: 8px 14px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);">
                ☁ Outlook Web
            </a>
            @endif
        </div>
    </div>

    @unless($forEmployee)
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 32px;">Je kunt inloggen op het dashboard om eventuele documenten, voorbereidingen of details te bekijken.</p>
    @endunless
    
    <div style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 24px; font-size: 13px; color: #9ca3af;">
        <p style="margin: 0 0 4px 0;">Met vriendelijke groet,</p>
        <strong style="color: #4b5563; font-weight: 700;">{{ $forEmployee ? 'Het GKR Klantportaal' : 'Het GKR Team' }}</strong>
    </div>

</body>
</html>