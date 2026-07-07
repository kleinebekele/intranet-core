<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Dieses Gerät merken" für die Zwei-Faktor-Abfrage.
 *
 * Beim Bestätigen mit Häkchen bekommt der Browser ein (von Laravel
 * verschlüsseltes) Cookie mit einem Zufalls-Token; serverseitig liegt der
 * Token-Hash im Cache. Nur wenn BEIDES zusammenpasst, entfällt die Abfrage —
 * ein gefälschtes Cookie allein reicht nicht.
 */
class TwoFactorTrust
{
    private const COOKIE = 'intranet_2fa_trusted';

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

        $token = Str::random(40);
        $minutes = $this->days() * 24 * 60;

        Cache::put($this->key($user, $token), true, now()->addMinutes($minutes));

        Cookie::queue(self::COOKIE, $user->id.'|'.$token, $minutes);
    }

    /** Ist dieses Gerät für den Benutzer (noch) vertrauenswürdig? */
    public function check(User $user, Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        [$id, $token] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, null);

        return (int) $id === $user->id
            && $token !== null
            && Cache::has($this->key($user, $token));
    }

    private function key(User $user, string $token): string
    {
        return "two-factor:trusted:{$user->id}:".hash('sha256', $token);
    }
}
