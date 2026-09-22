<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Teams-Channel, in den benachrichtigt werden kann.
 *
 * ⚠️ Die webhook_url ist ein PASSWORT: Die Workflow-URL trägt den Token im
 * Query-String. Darum Cast 'encrypted' (kein Klartext in DB-Dumps/Backups) und
 * niemals ins Repo. Preis des Casts: Bei APP_KEY-Verlust sind die URLs futsch –
 * bei einer Handvoll Channels verschmerzbar, dann neu eintragen.
 *
 * Hintergrund: Der klassische Office-365-Connector ("Incoming Webhook",
 * outlook.office.com + MessageCard) ist von Microsoft abgekündigt und Ende 2025
 * gestorben. Ersatz sind Teams-Workflows (Power Automate), URL auf
 * …logic.azure.com…
 */
class TeamsChannel extends Model
{
    protected $table = 'ekkon_teams_channels';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'webhook_url' => 'encrypted',
            'aktiv' => 'boolean',
        ];
    }

    /**
     * Zweiter Weg (seit 22.09.2026): `chat_id` gesetzt = Nachrichten gehen
     * direkt über Microsoft Graph im Namen des verbundenen Kontos
     * (TeamsGraphClient), nicht über den Workflow. Nur so lässt sich eine Datei
     * als echte Dateikarte anhängen; sie landet im OneDrive des Kontos (Organisationslink).
     */
    public function perGraph(): bool
    {
        return trim((string) $this->chat_id) !== '';
    }

    /** Ziel ist eine Person (E-Mail-Adresse) statt Chat/Kanal (19:…-ID). */
    public static function istPerson(string $ziel): bool
    {
        return ! str_contains($ziel, '19:') && filter_var(trim($ziel), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Für die Anzeige: Workflow / Graph → Chat/Kanal / Graph → Person. */
    public function weg(): string
    {
        if (! $this->perGraph()) {
            return 'Workflow';
        }

        return self::istPerson((string) $this->chat_id) ? 'Graph → Person' : 'Graph → Chat/Kanal';
    }

    /** @return HasMany<NotificationRoute, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(NotificationRoute::class, 'teams_channel_id');
    }
}
