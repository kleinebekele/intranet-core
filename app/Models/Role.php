<?php

namespace App\Models;

use App\Modules\Support\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Der Primärschlüssel ist ein sprechender String (z. B. 'teacher'),
     * kein automatisch hochzählendes Integer.
     */
    protected $primaryKey = 'role_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `quelle` ist mass-assignable, weil Abgleiche (Module) ihre Rollen per
     * firstOrCreate/updateOrCreate anlegen; im Panel wird sie nie gesetzt.
     */
    protected $fillable = ['role_id', 'name', 'quelle'];

    /**
     * `is_system` ist bewusst NICHT mass-assignable – System-Rollen werden
     * nur per Migration gesetzt und können im Panel nicht verändert werden.
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'plattformweit' => 'boolean'];
    }

    /** System-Rollen (z. B. admin, user) sind fest und dürfen nicht gelöscht werden. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Wird die Rolle von einem Abgleich (Modul) gepflegt? Dann ist die
     * Mitgliederpflege im Panel gesperrt – der nächste Lauf würde sie zurückdrehen.
     */
    public function istVerwaltet(): bool
    {
        return $this->quelle !== null && $this->quelle !== '';
    }

    /**
     * Bringt ein Modul diese Rolle mit (Manifest → `modules:sync`)? `modul` wird
     * wie `is_system` nur vom Core gesetzt, nie per Mass-Assignment.
     */
    public function gehoertZuModul(): bool
    {
        return $this->modul !== null && $this->modul !== '';
    }

    /**
     * Gilt die Rolle gerade? Handangelegte und System-Rollen immer; eine
     * Modulrolle nur, solange ihr Modul aktiv UND installiert ist. Plattformweite
     * Rollen (Lehrer, Schüler …) gelten immer – an ihnen hängen auch fremde Module.
     */
    public function istAktiv(): bool
    {
        return ! in_array($this->role_id, static::inaktiveSchluessel(), true);
    }

    /** Nur Rollen, die gerade gelten (siehe {@see istAktiv()}). */
    public function scopeAktiv(Builder $query): void
    {
        $query->whereNotIn($query->qualifyColumn('role_id'), static::inaktiveSchluessel() ?: ['__none__']);
    }

    /**
     * Schlüssel aller Rollen, deren Modul aus oder deinstalliert ist. Je
     * Anfrage einmal ermittelt (liegt im Container, damit Tests sauber bleiben).
     *
     * @return string[]
     */
    public static function inaktiveSchluessel(): array
    {
        if (app()->bound('rollen.inaktiv')) {
            return app('rollen.inaktiv');
        }

        $aktiveModule = Module::where('is_enabled', true)
            ->whereIn('key', app(ModuleRegistry::class)->keys())
            ->pluck('key')
            ->all();

        $inaktiv = static::query()
            ->whereNotNull('modul')
            ->where('plattformweit', false)
            ->whereNotIn('modul', $aktiveModule ?: ['__none__'])
            ->pluck('role_id')
            ->all();

        app()->instance('rollen.inaktiv', $inaktiv);

        return $inaktiv;
    }

    /** Nach dem Umschalten eines Moduls oder einem Sync neu ermitteln. */
    public static function aktivStandVergessen(): void
    {
        app()->forgetInstance('rollen.inaktiv');
    }

    /**
     * Alle Benutzer, die diese Rolle besitzen (n:n über user_roles).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id', 'role_id', 'id');
    }
}
