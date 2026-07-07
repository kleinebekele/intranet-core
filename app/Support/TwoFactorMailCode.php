<?php

namespace App\Support;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * E-Mail-Einmalcodes für die Zwei-Faktor-Anmeldung (Standard-Verfahren).
 *
 * Der Code lebt 10 Minuten im Cache (nur als Hash), maximal 5 Fehlversuche,
 * erneuter Versand frühestens nach 60 Sekunden.
 */
class TwoFactorMailCode
{
    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_SECONDS = 60;

    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($user), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Cache::put($this->key($user).':resend', true, self::RESEND_SECONDS);

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, self::TTL_MINUTES));
    }

    /** Nur senden, wenn kein Code mehr aktiv ist (z. B. beim Öffnen der Abfrage-Seite). */
    public function sendIfMissing(User $user): void
    {
        if (! Cache::has($this->key($user))) {
            $this->send($user);
        }
    }

    public function canResend(User $user): bool
    {
        return ! Cache::has($this->key($user).':resend');
    }

    public function verify(User $user, string $code): bool
    {
        $entry = Cache::get($this->key($user));

        if ($entry === null) {
            return false; // abgelaufen oder nie angefordert
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($user));

            return false;
        }

        if (Hash::check(preg_replace('/\D/', '', $code), $entry['hash'])) {
            Cache::forget($this->key($user));

            return true;
        }

        $entry['attempts']++;
        Cache::put($this->key($user), $entry, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }

    private function key(User $user): string
    {
        return "two-factor:mail:{$user->id}";
    }
}
