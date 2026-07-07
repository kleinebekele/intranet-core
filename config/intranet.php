<?php

return [

    /*
     * Zwei-Faktor-Authentifizierung (instanzweiter Schalter).
     *
     * TWO_FACTOR=on  → nach dem Passwort ist ein zweiter Faktor nötig:
     *                  standardmäßig ein Code per E-Mail; wer im Profil TOTP
     *                  (Authenticator-App) eingerichtet hat, nutzt stattdessen TOTP.
     * TWO_FACTOR=off → klassischer Login nur mit Passwort (Standard).
     *
     * Achtung: nur aktivieren, wenn alle Benutzer erreichbare E-Mail-Adressen
     * haben und der Mail-Versand (MAIL_*) konfiguriert ist.
     */
    'two_factor' => filter_var(env('TWO_FACTOR', false), FILTER_VALIDATE_BOOL),

];
