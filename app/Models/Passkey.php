<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein registrierter Passkey (WebAuthn-Zugangsschlüssel) eines Benutzers.
 */
class Passkey extends Model
{
    protected $fillable = ['name', 'credential_id', 'public_key', 'sign_count', 'aaguid'];

    protected $hidden = ['public_key'];

    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
            'zuletzt_benutzt_am' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
