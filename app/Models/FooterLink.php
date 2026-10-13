<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Link im Footer (Systemeinstellungen → Footer).
 *
 *  - extern: führt auf `url` (neuer Tab)
 *  - intern: eigene Seite unter /seite/{slug}, auch ohne Anmeldung erreichbar
 *    (Impressum und Datenschutz müssen vor dem Login lesbar sein). `inhalt` ist
 *    HTML aus dem Editor, `einbindung` wird roh darunter ausgegeben – für Bausteine,
 *    die ein Dienstleister per <script> liefert (Datenschutzerklärung o. ä.).
 *
 * Beides pflegen nur Administratoren; ausgegeben wird es ungefiltert.
 */
class FooterLink extends Model
{
    public const INTERN = 'intern';

    public const EXTERN = 'extern';

    protected $fillable = ['titel', 'art', 'url', 'slug', 'inhalt', 'einbindung', 'position', 'aktiv'];

    protected $casts = ['aktiv' => 'boolean'];

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }

    public function istIntern(): bool
    {
        return $this->art === self::INTERN;
    }

    public function href(): string
    {
        return $this->istIntern() ? route('seiten.show', $this->slug) : (string) $this->url;
    }

    /** Die aktiven Links in Reihenfolge – leer, solange die Tabelle fehlt (vor migrate). */
    public static function fuerFooter(): Collection
    {
        return Schema::hasTable('footer_links')
            ? static::aktiv()->orderBy('position')->orderBy('id')->get()
            : new Collection;
    }
}
