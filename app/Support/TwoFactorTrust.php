<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Dieses Gerät merken" für die Zwei-Faktor-Abfrage.
 *
 * Beim Bestätigen mit Häkchen bekommt der Browser ein (von Laravel
 * verschlüsseltes) Cookie mit einem Zufalls-Token; serverseitig liegt der
 * Token-Hash in der Tabelle `two_factor_trusted_devices`. Nur wenn BEIDES
 * zusammenpasst, entfällt die Abfrage — ein gefälschtes Cookie allein reicht
 * nicht.
 *
 * Der Server-Token liegt bewusst in einer eigenen Tabelle und NICHT im App-Cache:
 * so übersteht ein vertrautes Gerät `cache:clear`/`optimize:clear` und damit
 * jeden Deploy.
 */
class TwoFactorTrust
{
    private const COOKIE = 'intranet_2fa_trusted';

    private const TABLE = 'two_factor_trusted_devices';

    public function days(): int
    {
        return (int) config('intranet.two_factor_remember_days');
    }

    public function enabled(): bool
    {
        return $this->days() > 0;
    }

    /** Nach erfolgreicher Code-Eingabe: dieses Gerät für N Tage eintragen. */
    public function remember(User $user): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->pruneExpired();

        $token = Str::random(40);
        $minutes = $this->days() * 24 * 60;

        DB::table(self::TABLE)->insert([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'expires_at' => now()->addMinutes($minutes),
            'created_at' => now(),
        ]);

        Cookie::queue(self::COOKIE, $user->id.'|'.$token, $minutes);
    }

    /** Ist dieses Gerät für den Benutzer (noch) vertrauenswürdig? */
    public function check(User $user, Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        [$id, $token] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, null);

        if ((int) $id !== $user->id || $token === null) {
            return false;
        }

        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** Abgelaufene Einträge aufräumen, damit die Tabelle nicht anwächst. */
    private function pruneExpired(): void
    {
        DB::table(self::TABLE)->where('expires_at', '<=', now())->delete();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
