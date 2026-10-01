<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein empfangener Webhook-Aufruf, roh und unverändert. */
class WebhookEingang extends Model
{
    protected $table = 'ekkon_webhook_eingaenge';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * created_at selbst setzen: Der Spalten-Default (useCurrent) nimmt die Uhr der
     * Datenbank – auf MySQL in UTC –, gelesen wird aber in der App-Zeitzone. Die
     * Eingänge standen dadurch zwei Stunden zu früh in der Liste.
     */
    protected static function booted(): void
    {
        static::creating(function (self $eingang): void {
            $eingang->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'created_at' => 'datetime',
            'verarbeitet_am' => 'datetime',
        ];
    }

    public function quelle(): BelongsTo
    {
        return $this->belongsTo(WebhookQuelle::class, 'quelle_id');
    }

    /** Body hübsch, wenn er JSON ist – sonst so, wie er kam. */
    public function bodyLesbar(): string
    {
        $body = (string) $this->body;
        $json = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($json)
            ? (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $body;
    }
}
