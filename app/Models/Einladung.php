<?php

namespace App\Models;

use App\Notifications\EinladungenWarten;
use App\Notifications\WelcomeNewUser;
use App\Support\Zustellbarkeit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Password;

/**
 * Eine vorgemerkte Einladung: „Diesem Benutzer sollte jemand einen Zugangslink
 * schicken – sobald ein Mensch zustimmt."
 *
 * Der Versand selbst passiert erst in {@see freigeben()}. Bis dahin ist nichts
 * geschehen, was sich nicht zurücknehmen ließe.
 */
class Einladung extends Model
{
    public const WARTEND = 'wartend';

    public const VERSCHICKT = 'verschickt';

    public const VERWORFEN = 'verworfen';

    /** Künstliche Adresse (`…@schueler.intern`) – eine Mail kann dort nie ankommen. */
    public const UNZUSTELLBAR = 'unzustellbar';

    protected $table = 'einladungen';

    protected $fillable = ['user_id', 'status', 'quelle'];

    protected $casts = ['entschieden_am' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWartend(Builder $query): Builder
    {
        return $query->where('status', self::WARTEND);
    }

    /**
     * Eine Einladung vormerken. Idempotent: Ein zweiter Aufruf für denselben
     * Benutzer legt keine zweite Zeile an, und eine bereits verschickte
     * Einladung wird nicht wieder auf „wartend" zurückgesetzt.
     *
     * Benutzer mit künstlicher Adresse werden gar nicht erst vorgemerkt – sie
     * würden die Liste füllen, ohne dass je etwas verschickt werden könnte.
     */
    public static function vormerken(User $user, ?string $quelle = null): ?self
    {
        if (! Zustellbarkeit::zustellbar($user->email)) {
            return null;
        }

        $vorhanden = static::where('user_id', $user->id)->first();

        if ($vorhanden) {
            return $vorhanden;
        }

        return static::create([
            'user_id' => $user->id,
            'status' => self::WARTEND,
            'quelle' => $quelle,
        ]);
    }

    /**
     * Die Administratoren wissen lassen, dass etwas auf sie wartet.
     *
     * Eine Mail mit der Gesamtzahl an alle Administratoren – nicht eine je
     * vorgemerkter Einladung. Ohne wartende Einladungen passiert nichts.
     *
     * @return int Anzahl benachrichtigter Administratoren
     */
    public static function adminsBenachrichtigen(?string $quelle = null): int
    {
        $anzahl = static::wartend()->count();

        if ($anzahl === 0) {
            return 0;
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(new EinladungenWarten($anzahl, $quelle));
        }

        return $admins->count();
    }

    /**
     * Jetzt wirklich einladen: Passwort-Link erzeugen und verschicken.
     *
     * Die Mail geht über den normalen Weg – also in den Ausgangskorb und von
     * dort gedrosselt raus. Bei hunderten Einladungen ist genau das erwünscht.
     */
    public function freigeben(?User $entschiedenVon = null): bool
    {
        if ($this->status !== self::WARTEND) {
            return false;
        }

        if (! Zustellbarkeit::zustellbar($this->user->email)) {
            $this->abschliessen(self::UNZUSTELLBAR, $entschiedenVon);

            return false;
        }

        $this->user->notify(new WelcomeNewUser(Password::broker()->createToken($this->user)));
        $this->abschliessen(self::VERSCHICKT, $entschiedenVon);

        return true;
    }

    public function verwerfen(?User $entschiedenVon = null): void
    {
        $this->abschliessen(self::VERWORFEN, $entschiedenVon);
    }

    private function abschliessen(string $status, ?User $entschiedenVon): void
    {
        $this->forceFill([
            'status' => $status,
            'entschieden_am' => now(),
            'entschieden_von' => $entschiedenVon?->id,
        ])->save();
    }
}
