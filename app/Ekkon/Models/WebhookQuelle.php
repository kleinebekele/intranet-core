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
        return [
            'aktiv' => 'boolean',
            'lebenszeichen_am' => 'datetime',
            'waechter_alarm_am' => 'datetime',
            'signatur_geheimnis' => 'encrypted',
        ];
    }

    /** Wie weit der Zeitstempel eines signierten Aufrufs abweichen darf (Sekunden). */
    public const SIGNATUR_TOLERANZ = 300;

    public function verlangtSignatur(): bool
    {
        return filled($this->signatur_geheimnis);
    }

    /**
     * Signatur eines Aufrufs prüfen: Kopfzeilen `X-Intranet-Zeit` (Unix-Sekunden)
     * und `X-Intranet-Signatur: sha256=<hex>` mit
     * hex = HMAC-SHA256(geheimnis, zeit + "." + body).
     */
    public function signaturGueltig(?string $zeit, ?string $signatur, string $body): bool
    {
        if (! $this->verlangtSignatur() || $zeit === null || $signatur === null || ! ctype_digit($zeit)) {
            return false;
        }

        if (abs(time() - (int) $zeit) > self::SIGNATUR_TOLERANZ) {
            return false;
        }

        $erwartet = 'sha256='.hash_hmac('sha256', $zeit.'.'.$body, $this->signatur_geheimnis);

        return hash_equals($erwartet, strtolower(trim($signatur)));
    }

    public static function neuesGeheimnis(): string
    {
        return bin2hex(random_bytes(32));
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
