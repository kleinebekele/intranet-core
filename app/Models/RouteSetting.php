<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sprechende Adresse und fester Titel einer Route (Verwaltung → SEO).
 *
 * Wird bei JEDEM Seitenaufruf gebraucht (der Router schreibt die Adressen beim
 * Hochfahren um), darum im Cache – und der Cache wird bei jeder Änderung
 * verworfen.
 */
class RouteSetting extends Model
{
    protected $table = 'route_settings';

    protected $primaryKey = 'route_name';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'stamm_ignorieren' => 'boolean',
    ];

    private const CACHE_KEY = 'routen.einstellungen';

    /**
     * Alle Einträge, nach Routen-Namen.
     *
     * @return array<string, array{pfad:?string, titel:?string, stamm_ignorieren:bool}>
     */
    public static function alle(): array
    {
        // Existenzprüfung VOR dem Cache: Während der ersten Migration gibt es
        // die Tabelle noch nicht – ein hier gecachtes leeres Ergebnis bliebe
        // für immer bestehen.
        if (! Schema::hasTable('route_settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['route_name', 'pfad', 'titel', 'stamm_ignorieren'])
            ->keyBy('route_name')
            ->map(fn ($z) => [
                'pfad' => $z->pfad,
                'titel' => $z->titel,
                'stamm_ignorieren' => (bool) $z->stamm_ignorieren,
            ])
            ->all()
        );
    }

    /** Fester Titel einer Route, falls gesetzt. */
    public static function titel(?string $routeName): ?string
    {
        return $routeName ? (self::alle()[$routeName]['titel'] ?? null) : null;
    }

    public static function cacheVerwerfen(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::cacheVerwerfen());
        static::deleted(fn () => self::cacheVerwerfen());
    }
}
