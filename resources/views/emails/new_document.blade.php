<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nieuw document - GKR Klantportaal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #ffffff;">

    <h2 style="color: #011936; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
        Beste {{ $clientName }},
    </h2>

    @if($versionNumber > 1)
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">GKR heeft een nieuwe versie van een document voor je klaargezet in het klantportaal.</p>
    @else
        <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">GKR heeft een nieuw document voor je klaargezet in het klantportaal.</p>
    @endif

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; margin: 24px 0; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
        <p style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #011936;">{{ $documentName }}</p>
        <p style="margin: 0 0 16px 0; font-size: 13px; color: #6b7280;">Versie {{ $versionNumber }}</p>

        {{-- Het bestand zelf zit bewust niet in de mail --}}
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #4b5563;">Log in om het document te bekijken, een reactie te plaatsen of akkoord te geven.</p>

        <a href="{{ $documentUrl }}" target="_blank" style="background-color: #011936; color: #ffffff; text-decoration: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block;">
            Bekijk het document
        </a>
    </div>

    <p style="font-size: 12px; color: #9ca3af;">GKR Digital Agency · Klantportaal</p>
</body>
</html>