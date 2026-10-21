<?php

namespace App\Support;

use App\Models\AuditEintrag;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Audit-Log schreiben: wer hat wann was getan.
 *
 * Aufruf aus dem Core und aus Modulen:
 *
 *     Audit::schreiben('benutzer.gesperrt', 'Schule verlassen', betroffener: $user);
 *     Audit::schreiben('kantine.bestellung_storniert', "Bestellung #$id", ziel: "Bestellung $id");
 *
 * Der Akteur ist der angemeldete Benutzer, wenn nichts anderes übergeben wird
 * (im Task/Cron: keiner). Module bringen ihre Aktionsschlüssel mit Präfix
 * (Modulname) mit und melden den Klartext dazu einmalig im Provider an:
 *
 *     Audit::benennen(['kantine.bestellung_storniert' => 'Kantine: Bestellung storniert']);
 *
 * Grundsatz: Es landen nie Passwörter, Codes oder Tokens hier – nur wer, was,
 * wen und ein knapper Klartext. Was in `daten` liegt, erscheint 1:1 im Admin.
 */
final class Audit
{
    /** @var array<string, string> */
    private static array $bezeichnungen = [];

    /** @var array<string, string> Aktionsschlüssel → Modul-Key (aus dem Namensraum des Anmelders) */
    private static array $module = [];

    /** Modul-Key für die eigenen Aktionen des Cores. */
    public const CORE = 'core';

    /**
     * @param  array<string, mixed>  $daten  Details für die Anzeige (Vorher/Nachher o. ä.)
     * @param  User|false|null  $akteur  null = angemeldeter Benutzer, false = ausdrücklich niemand (System)
     */
    public static function schreiben(
        string $aktion,
        ?string $beschreibung = null,
        ?User $betroffener = null,
        array $daten = [],
        ?string $ziel = null,
        User|false|null $akteur = null,
    ): AuditEintrag {
        if ($akteur === null) {
            $akteur = Auth::user();
        }
        if ($akteur === false) {
            $akteur = null;
        }

        return AuditEintrag::create([
            'aktion' => $aktion,
            'user_id' => $akteur?->id,
            'akteur' => $akteur?->name,
            'betroffener_id' => $betroffener?->id,
            'betroffener' => $betroffener?->name,
            'ziel' => $ziel,
            'beschreibung' => $beschreibung,
            'daten' => $daten === [] ? null : $daten,
            'ip' => self::ip(),
        ]);
    }

    /**
     * Klartext für Aktionsschlüssel anmelden (Module, im Provider-boot()).
     *
     * @param  array<string, string>  $bezeichnungen
     */
    public static function benennen(array $bezeichnungen): void
    {
        self::$bezeichnungen = [...self::$bezeichnungen, ...$bezeichnungen];

        // Zu welchem Modul gehören die Aktionen? Aus dem Namensraum des Aufrufers
        // (Intranet\Modules\<Name>\…) – so müssen Module nichts zusätzlich angeben.
        $klasse = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? '';
        $teile = explode('\\', $klasse);
        $modul = ($teile[0] ?? '') === 'Intranet' && ($teile[1] ?? '') === 'Modules' && isset($teile[2])
            ? strtolower($teile[2])
            : self::CORE;
        foreach (array_keys($bezeichnungen) as $aktion) {
            self::$module[$aktion] = $modul;
        }
    }

    /** Modul-Key einer Aktion: core, ein Modul-Key oder null (unbekannt, z. B. Modul entfernt). */
    public static function modulVon(string $aktion): ?string
    {
        return self::$module[$aktion] ?? (isset(AuditEintrag::AKTIONEN[$aktion]) ? self::CORE : null);
    }

    /**
     * Module mit Aktionen für den Filter: Key → Anzeigename (Core zuerst, dann nach Name).
     *
     * @return array<string, string>
     */
    public static function bekannteModule(): array
    {
        $keys = array_unique([self::CORE, ...array_values(self::$module)]);
        $namen = \App\Models\Module::whereIn('key', $keys)->pluck('name', 'key')->all();
        $liste = [];
        foreach ($keys as $key) {
            $liste[$key] = $key === self::CORE ? 'Intranet (Kern)' : ($namen[$key] ?? ucfirst($key));
        }
        $kern = [self::CORE => $liste[self::CORE]];
        unset($liste[self::CORE]);
        asort($liste);

        return $kern + $liste;
    }

    /**
     * Aktionsschlüssel eines Moduls.
     *
     * @return list<string>
     */
    public static function aktionenVon(string $modul): array
    {
        return array_values(array_filter(array_keys(self::bekannteAktionen()), fn ($a) => self::modulVon($a) === $modul));
    }

    public static function bezeichnung(string $aktion): string
    {
        return self::$bezeichnungen[$aktion] ?? AuditEintrag::AKTIONEN[$aktion] ?? $aktion;
    }

    /**
     * Alle bekannten Aktionen (Core + Module) für den Filter, nach Klartext sortiert.
     *
     * @return array<string, string>
     */
    public static function bekannteAktionen(): array
    {
        $alle = [...AuditEintrag::AKTIONEN, ...self::$bezeichnungen];
        asort($alle);

        return $alle;
    }

    private static function ip(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return IpKuerzung::kuerzen(request()?->ip());
    }
}
