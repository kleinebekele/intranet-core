<!DOCTYPE html>
<html lang="de">
<body style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; padding: 24px;">
    <h1 style="font-size: 18px;">Dein Anmeldecode</h1>
    <p>Zum Abschluss der Anmeldung im Intranet gib bitte diesen Code ein:</p>
    <p style="font-size: 32px; font-weight: bold; letter-spacing: 6px; background: #f3f4f6; display: inline-block; padding: 12px 24px; border-radius: 8px;">
        {{ $code }}
    </p>
    <p style="color: #6b7280; font-size: 13px;">
        Der Code ist {{ $ttlMinutes }} Minuten gültig.<br>
        Wenn du dich gerade nicht anmelden wolltest, kannst du diese E-Mail ignorieren —
        ohne dein Passwort kann niemand den Code verwenden.
    </p>
</body>
</html>
