<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Zur Laufzeit änderbare Einstellungen (Verwaltung → Einstellungen).
 *
 * Wird auf jeder Seite gelesen (Titel, Favicon), darum im Cache gehalten und
 * bei jeder Änderung verworfen.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'schluessel';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    private const CACHE_KEY = 'settings.alle';

    /** Alle Einstellungen als Schlüssel/Wert-Paare. */
    public static function alle(): array
    {
        // Die Existenzprüfung steht bewusst VOR dem Cache: Läuft die erste
        // Migration, gibt es die Tabelle noch nicht – ein hier gecachtes leeres
        // Ergebnis würde für immer bestehen bleiben und alle Einstellungen
        // unsichtbar machen.
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::pluck('wert', 'schluessel')->all()
        );
    }

    public static function get(string $schluessel, mixed $standard = null): mixed
    {
        $wert = self::alle()[$schluessel] ?? null;

        // Leerer String zählt als „nicht gesetzt" – so räumt ein geleertes
        // Formularfeld die Einstellung weg, statt einen leeren Titel zu setzen.
        return ($wert === null || $wert === '') ? $standard : $wert;
    }

    public static function set(string $schluessel, mixed $wert): void
    {
        static::updateOrCreate(['schluessel' => $schluessel], ['wert' => $wert]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function vergessen(string $schluessel): void
    {
        static::where('schluessel', $schluessel)->delete();

        Cache::forget(self::CACHE_KEY);
    }
}
