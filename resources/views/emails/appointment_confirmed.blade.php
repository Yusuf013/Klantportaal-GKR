<!DOCTYPE html>
<html>
<head>
    <title>Gesprek Bevestigd</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #1a56db;">Beste relatie,</h2>
    <p>Het geplande gesprek binnen het GKR Klantportaal is definitief goedgekeurd en ingepland.</p>
    
    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #374151;">{{ $appointment->title }}</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 6px 0; color: #6b7280; width: 100px;"><strong>Type:</strong></td>
                <td style="padding: 6px 0; color: #111827;">{{ $appointment->type }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;"><strong>Datum:</strong></td>
                <td style="padding: 6px 0; color: #111827;">{{ \Carbon\Carbon::parse($appointment->start_time)->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;"><strong>Tijd:</strong></td>
                <td style="padding: 6px 0; color: #111827;">
                    {{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($appointment->end_time)->format('H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <p>Log in op het dashboard om eventuele documenten of details te bekijken.</p>
    <p style="margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px; font-size: 0.9em; color: #9ca3af;">
        Met vriendelijke groet,<br>
        <strong>GKR Team</strong>
    </p>
</body>
</html>