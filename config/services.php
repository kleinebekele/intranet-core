<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Anmeldung mit dem Microsoft-Konto (Entra ID / Microsoft 365).
     *
     * Ohne MS_TENANT_ID / MS_CLIENT_ID / MS_CLIENT_SECRET ist die Funktion
     * komplett aus: Auf der Anmeldeseite taucht dann gar kein Microsoft-Knopf
     * auf. Jede Instanz entscheidet also über ihre .env, ob sie SSO nutzt.
     *
     * MS_GRUPPEN steuert, wer NEU angelegt werden darf: eine oder mehrere
     * Objekt-IDs (GUIDs) von Microsoft-365-Gruppen, mit Komma getrennt. Wer
     * beim Anmelden in keiner davon ist und noch kein Intranet-Konto hat, wird
     * abgewiesen. Ohne Eintrag wird niemand automatisch angelegt – dann kommen
     * nur Benutzer herein, die es im Intranet schon gibt.
     *
     * MS_NEUE_ROLLEN sind die Rollen, die ein so angelegtes Konto bekommt
     * (Komma-getrennt, z. B. staff). Die Rolle user vergibt der Core ohnehin.
     */
    'microsoft' => [
        'tenant' => env('MS_TENANT_ID'),
        'client_id' => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'gruppen' => env('MS_GRUPPEN', ''),
        'neue_rollen' => env('MS_NEUE_ROLLEN', ''),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
