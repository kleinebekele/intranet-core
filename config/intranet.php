<?php

return [

    /*
     * Zwei-Faktor-Authentifizierung.
     *
     * 2FA ist immer verfügbar: jeder Benutzer aktiviert sie freiwillig in
     * seinem Profil (Standard-Faktor: Code per E-Mail; optional TOTP per
     * Authenticator-App, z. B. Vaultwarden).
     *
     * FORCE_2FA_ADMINS=false    → Admins dürfen 2FA abschalten (Standard:
     *                             für Admins verpflichtend).
     * FORCE_2FA=true            → 2FA ist für ALLE Benutzer verpflichtend
     *                             (individuelles Abschalten nicht möglich).
     * TWO_FACTOR_REMEMBER_DAYS  → "Dieses Gerät merken" bei der Code-Abfrage:
     *                             so viele Tage keine erneute Abfrage auf dem
     *                             Gerät. 0 = Funktion aus, bei jedem Login fragen.
     *
     * Voraussetzung für Mail-Codes: funktionierender Mail-Versand (MAIL_*).
     */
    /*
     * Selbst-Registrierung (/register).
     *
     * Standard: AUS — Benutzer werden vom Admin angelegt (Verwaltung → Benutzer)
     * oder importiert. REGISTRATION_ENABLED=true öffnet die Registrierung.
     *
     * Ausnahme unabhängig vom Schalter: solange noch KEIN Benutzer existiert
     * (frische Installation), ist die Registrierung offen, damit der erste
     * Admin angelegt werden kann.
     */
    'registration_enabled' => filter_var(env('REGISTRATION_ENABLED', false), FILTER_VALIDATE_BOOL),

    'two_factor_forced' => filter_var(env('FORCE_2FA', false), FILTER_VALIDATE_BOOL),

    'two_factor_admins' => filter_var(env('FORCE_2FA_ADMINS', true), FILTER_VALIDATE_BOOL),

    'two_factor_remember_days' => max(0, (int) env('TWO_FACTOR_REMEMBER_DAYS', 30)),

    /*
     * Audit-Log (Verwaltung → Audit): Einträge älter als so viele Tage werden
     * nachts gelöscht. 0 = nie aufräumen.
     */
    'audit_aufbewahrung_tage' => max(0, (int) env('AUDIT_AUFBEWAHRUNG_TAGE', 365)),

    /*
     * IPs der Reverse-Proxys, deren X-Forwarded-For Laravel glauben darf
     * (kommagetrennt). Leer = keinem – richtig, wenn nginx die Client-IP per
     * `set_real_ip_from` schon selbst einsetzt.
     */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
