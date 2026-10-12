<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $approved ? 'Akkoord op een document' : 'Nieuwe reactie op een document' }} - GKR Klantportaal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #ffffff;">

    <h2 style="color: #011936; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
        @if($approved)
            {{ $clientName }} heeft akkoord gegeven
        @else
            Nieuwe reactie van {{ $clientName }}
        @endif
    </h2>

    @if($approved)
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">{{ $clientName }} heeft in het klantportaal digitaal akkoord gegeven op een document. Jullie kunnen verder met de volgende stap.</p>
    @else
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">{{ $clientName }} heeft in het klantportaal gereageerd op een document.</p>
    @endif

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; margin: 24px 0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
        <p style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #011936;">{{ $documentName }}</p>
        @if($versionNumber)
            <p style="margin: 0 0 16px 0; font-size: 13px; color: #6b7280;">Versie {{ $versionNumber }}</p>
        @endif

        {{-- De tekst van de reactie staat bewust niet in de mail --}}
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #4b5563;">
            {{ $approved ? 'Log in om het akkoord te bekijken.' : 'Log in om de reactie te lezen en te beantwoorden.' }}
        </p>

        <a href="{{ $documentUrl }}" target="_blank" style="background-color: #011936; color: #ffffff; text-decoration: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block;">
            Open het document
        </a>
    </div>

    @unless($approved)
        <p style="font-size: 12px; color: #9ca3af; margin-top: 24px;">
            Je krijgt deze melding één keer per document. Komen er meer reacties bij voordat GKR heeft gereageerd, dan sturen we geen nieuwe e-mail.
        </p>
    @endunless

    <p style="font-size: 12px; color: #9ca3af;">GKR Digital Agency · Klantportaal</p>
</body>
</html>