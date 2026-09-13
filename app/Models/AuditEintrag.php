<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag im Audit-Log: wer hat wann was getan (siehe Migration
 * create_audit_log_table). Geschrieben wird über \App\Support\Audit.
 */
class AuditEintrag extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $fillable = [
        'aktion', 'user_id', 'akteur', 'betroffener_id', 'betroffener',
        'ziel', 'beschreibung', 'daten', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'daten' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Klartext je Aktionsschlüssel des Core. Module ergänzen ihre Schlüssel
     * über Audit::benennen(); unbekannte Schlüssel werden roh angezeigt.
     */
    public const AKTIONEN = [
        'anmeldung' => 'Anmeldung',
        'anmeldung.fehlgeschlagen' => 'Anmeldung fehlgeschlagen',
        'anmeldung.gesperrt' => 'Anmeldung gesperrt (zu viele Versuche)',
        'abmeldung' => 'Abmeldung',
        'registrierung' => 'Registrierung',
        'passwort.zurueckgesetzt' => 'Passwort zurückgesetzt',
        'passwort.geaendert' => 'Passwort geändert',
        'passwort.link' => 'Passwort-Link verschickt',
        'zweifaktor.aktiviert' => '2FA aktiviert',
        'zweifaktor.deaktiviert' => '2FA deaktiviert',
        'zweifaktor.totp' => 'TOTP eingerichtet',
        'zweifaktor.totp_entfernt' => 'TOTP entfernt',
        'profil.geaendert' => 'Profil geändert',
        'benutzer.angelegt' => 'Benutzer angelegt',
        'benutzer.geaendert' => 'Benutzer geändert',
        'benutzer.geloescht' => 'Benutzer gelöscht',
        'benutzer.gesperrt' => 'Benutzer gesperrt',
        'benutzer.entsperrt' => 'Benutzer entsperrt',
        'benutzer.anmeldeweg' => 'Anmeldeweg umgestellt',
        'benutzer.totp_zurueckgesetzt' => 'TOTP zurückgesetzt (Admin)',
        'rolle.angelegt' => 'Rolle angelegt',
        'rolle.geaendert' => 'Rolle geändert',
        'rolle.geloescht' => 'Rolle gelöscht',
        'rolle.zuweisungen_aufgehoben' => 'Rollen-Zuweisungen aufgehoben',
        'modul.umgeschaltet' => 'Modul an/aus',
        'modul.sichtbarkeit' => 'Modul-Sichtbarkeit geändert',
        'modul.entfernt' => 'Modul entfernt',
        'einstellungen.gespeichert' => 'Einstellungen gespeichert',
        'mailkonto.angelegt' => 'SMTP-Absender angelegt',
        'mailkonto.geaendert' => 'SMTP-Absender geändert',
        'mailkonto.geloescht' => 'SMTP-Absender gelöscht',
    ];

    /** Aktionen, die etwas Unerwünschtes festhalten – in der Liste rot. */
    public const WARNUNGEN = ['anmeldung.fehlgeschlagen', 'anmeldung.gesperrt'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function betroffenerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'betroffener_id');
    }

    public function aktionText(): string
    {
        return \App\Support\Audit::bezeichnung($this->aktion);
    }

    public function istWarnung(): bool
    {
        return in_array($this->aktion, self::WARNUNGEN, true);
    }

    /** Einträge, in denen ein Benutzer als Akteur ODER Betroffener vorkommt. */
    public function scopeZuBenutzer(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $userId)
            ->orWhere('betroffener_id', $userId));
    }
}
