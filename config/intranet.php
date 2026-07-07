<?php

return [

    /*
     * Zwei-Faktor-Authentifizierung.
     *
     * 2FA ist immer verfügbar: jeder Benutzer aktiviert sie freiwillig in
     * seinem Profil (Standard-Faktor: Code per E-Mail; optional TOTP per
     * Authenticator-App, z. B. Vaultwarden).
     *
     * FORCE_2FA=true            → 2FA ist für ALLE Benutzer verpflichtend
     *                             (individuelles Abschalten nicht möglich).
     * TWO_FACTOR_REMEMBER_DAYS  → "Dieses Gerät merken" bei der Code-Abfrage:
     *                             so viele Tage keine erneute Abfrage auf dem
     *                             Gerät. 0 = Funktion aus, bei jedem Login fragen.
     *
     * Voraussetzung für Mail-Codes: funktionierender Mail-Versand (MAIL_*).
     */
    'two_factor_forced' => filter_var(env('FORCE_2FA', false), FILTER_VALIDATE_BOOL),

    'two_factor_remember_days' => max(0, (int) env('TWO_FACTOR_REMEMBER_DAYS', 30)),

];
