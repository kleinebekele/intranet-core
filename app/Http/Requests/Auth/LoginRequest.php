<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Audit;
use App\Support\Microsoft\MicrosoftSso;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Erst prüfen, dann anmelden – nicht anmelden und wieder rauswerfen.
        // Sonst stünde jeder abgewiesene Versuch als „Anmeldung" + „Abmeldung"
        // im Audit-Log und der Zeitpunkt der letzten Anmeldung wäre falsch.
        $guard = Auth::guard('web');

        if (! $guard->validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());
            $this->fehlversuch('Falsches Passwort oder unbekannte Adresse.');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();

        // Konten, die über Microsoft laufen: Das Passwort stimmt zwar noch,
        // ist aber nicht mehr der vorgesehene Weg. Greift nur, solange die
        // Microsoft-Anmeldung überhaupt eingerichtet ist – sonst käme
        // niemand mehr herein, wenn sie einmal abgeschaltet wird.
        if ($user->nurUeberMicrosoft() && app(MicrosoftSso::class)->aktiv()) {
            $this->fehlversuch('Passwort richtig, Konto meldet sich aber nur über Microsoft an.', $user);

            throw ValidationException::withMessages([
                'email' => trans('auth.nur_microsoft'),
            ]);
        }

        // Gesperrte Konten: Das Passwort stimmt, trotzdem ist hier Schluss.
        if ($user->istGesperrt()) {
            RateLimiter::hit($this->throttleKey());
            $this->fehlversuch('Passwort richtig, Konto ist gesperrt.', $user);

            throw ValidationException::withMessages([
                'email' => trans('auth.gesperrt'),
            ]);
        }

        $guard->login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /** Abgewiesenen Versuch ins Audit-Log schreiben (ohne das Passwort). */
    private function fehlversuch(string $grund, ?User $user = null): void
    {
        $email = (string) $this->string('email');

        Audit::schreiben(
            'anmeldung.fehlgeschlagen',
            $grund,
            $user ?? User::query()->where('email', $email)->first(),
            ['email' => $email],
            akteur: false,
        );
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
