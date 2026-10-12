<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Je account voor het GKR Klantportaal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #ffffff;">

    <h2 style="color: #011936; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 16px;">
        Beste {{ $name }},
    </h2>

    <p style="font-size: 15px; color: #4b5563; margin-bottom: 24px;">
        GKR heeft een account voor je aangemaakt in het klantportaal. Daar volg je de voortgang van je projecten, bekijk je documenten, plan je afspraken en chat je met het team.
    </p>

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 24px; border-radius: 16px; margin: 24px 0;">
        <p style="margin: 0 0 6px 0; font-size: 14px; color: #4b5563;">Je logt in met:</p>
        <p style="margin: 0 0 20px 0; font-size: 14px; color: #011936; font-weight: 700;">{{ $email }}</p>

        <a href="{{ $url }}" target="_blank" style="background-color: #011936; color: #ffffff; text-decoration: none; padding: 10px 18px; font-size: 13px; font-weight: 700; border-radius: 10px; display: inline-block;">
            Wachtwoord instellen
        </a>

        <p style="margin: 20px 0 0 0; font-size: 12px; color: #6b7280;">
            Deze link werkt één keer en is {{ $validDays }} dagen geldig. Verlopen? Vraag GKR om een nieuwe uitnodiging.
        </p>
    </div>

    <p style="font-size: 12px; color: #9ca3af; margin-top: 24px;">
        Verwachtte je deze mail niet? Dan kun je hem negeren; er gebeurt niets zonder dat je op de knop klikt.
    </p>

    <p style="font-size: 12px; color: #9ca3af;">GKR Digital Agency, Breda</p>
</body>
</html>