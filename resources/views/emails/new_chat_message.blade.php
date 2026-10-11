<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nieuw bericht - GKR Klantportaal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #ffffff;">

    <h2 style="color: #011936; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
        @if($forGkr)
            Nieuw bericht van {{ $clientName }}
        @else
            Beste {{ $clientName }},
        @endif
    </h2>

    @if($forGkr)
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">{{ $clientName }} heeft een bericht gestuurd via de chat in het klantportaal.</p>
    @else
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">GKR heeft je een bericht gestuurd via de chat in het klantportaal.</p>
    @endif

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; margin: 24px 0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
        {{-- De tekst van het bericht staat bewust niet in de mail --}}
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #4b5563;">Log in om het bericht te lezen en te beantwoorden.</p>

        <a href="{{ $chatUrl }}" target="_blank" style="background-color: #011936; color: #ffffff; text-decoration: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block;">
            Open de chat
        </a>
    </div>

    <p style="font-size: 12px; color: #9ca3af; margin-top: 24px;">
        Je krijgt deze melding één keer. Komen er meer berichten bij voordat je de chat hebt geopend, dan sturen we geen nieuwe e-mail.
    </p>

    <p style="font-size: 12px; color: #9ca3af;">GKR Digital Agency · Klantportaal</p>
</body>
</html>