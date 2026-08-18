<?php

/*
|--------------------------------------------------------------------------
| Meldungen rund um die Anmeldung
|--------------------------------------------------------------------------
|
| Bewusst ohne Hinweis darauf, ob die E-Mail-Adresse existiert: Sonst könnte
| man über die Anmeldemaske herausfinden, wer ein Konto hat.
|
*/

return [

    'failed' => 'Diese Zugangsdaten passen zu keinem Konto.',
    'password' => 'Das eingegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuche es in :seconds Sekunden erneut.',

    // Hier wird bewusst KLAR gesagt, dass das Konto gesperrt ist: Wer bis
    // hierhin kommt, kennt sein Passwort – ihn im Unklaren zu lassen, würde nur
    // Ratlosigkeit und Anrufe erzeugen, ohne irgendetwas zu schützen.
    'gesperrt' => 'Dieses Konto ist gesperrt. Bitte wende dich an die Verwaltung.',

    // Auch hier ist Klartext besser als Rätselraten: Das Passwort stimmt ja,
    // es ist nur nicht mehr der richtige Weg.
    'nur_microsoft' => 'Dieses Konto läuft über Microsoft. Bitte melde dich auf der Anmeldeseite über "Mit Microsoft anmelden" an – ein Passwort brauchst du dafür nicht.',

];
