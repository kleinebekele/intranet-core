<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Absender, der uns per Webhook etwas schicken darf. Der Schlüssel steht
 * in der URL und ist damit das Passwort – nur Admins sehen ihn.
 */
class WebhookQuelle extends Model
{
    protected $table = 'ekkon_webhook_quellen';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean', 'lebenszeichen_am' => 'datetime', 'waechter_alarm_am' => 'datetime'];
    }

    public function eingaenge(): HasMany
    {
        return $this->hasMany(WebhookEingang::class, 'quelle_id');
    }

    public function url(): string
    {
        return route('ekkon.webhook.empfangen', $this->schluessel);
    }

    /** Abfrage-Adresse für den externen Wächter (GET, gleicher Schlüssel). */
    public function lebenszeichenUrl(): string
    {
        return route('ekkon.webhook.lebenszeichen', $this->schluessel);
    }

    public static function neuerSchluessel(): string
    {
        return bin2hex(random_bytes(24));
    }
}
