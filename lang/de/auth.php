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

];
