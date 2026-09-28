<?php

namespace App\Modules\Support;

use Illuminate\Http\Request;

/**
 * Wie weit darf eine Rolle auf einer Modulseite gehen? Jede Stufe schließt
 * die darunter ein: lesen < bearbeiten < verwalten.
 *
 *  - lesen:      Seiten aufrufen, PDFs/CSV ziehen
 *  - bearbeiten: Vorhandenes ändern und Arbeitsschritte ausführen (abhaken, freigeben …)
 *  - verwalten:  zusätzlich anlegen und löschen
 *
 * Die Stufe hängt an der Zuordnung Menüpunkt ↔ Rolle (`module_menu_item_role.stufe`);
 * `EnsureModuleAccess` setzt sie für jede Modulroute durch (siehe {@see benoetigt()}).
 */
enum Zugriffsstufe: string
{
    case Lesen = 'lesen';
    case Bearbeiten = 'bearbeiten';
    case Verwalten = 'verwalten';

    /** Request-Attribut, unter dem die Middleware die Stufe des Benutzers ablegt. */
    public const ATTRIBUT = 'zugriffsstufe';

    public function rang(): int
    {
        return match ($this) {
            self::Lesen => 1,
            self::Bearbeiten => 2,
            self::Verwalten => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Lesen => 'Lesen',
            self::Bearbeiten => 'Bearbeiten',
            self::Verwalten => 'Verwalten',
        };
    }

    /** Reicht diese Stufe für `$noetig`? */
    public function reichtFuer(self|string $noetig): bool
    {
        return $this->rang() >= self::aus($noetig)->rang();
    }

    public static function aus(self|string $stufe): self
    {
        return $stufe instanceof self ? $stufe : self::from($stufe);
    }

    /**
     * Die höchste Stufe aus einer Menge – null, wenn die Menge leer ist.
     *
     * @param  iterable<self|string|null>  $stufen
     */
    public static function hoechste(iterable $stufen): ?self
    {
        $beste = null;
        foreach ($stufen as $stufe) {
            if ($stufe === null || $stufe === '') {
                continue;
            }
            $stufe = self::tryFrom($stufe instanceof self ? $stufe->value : $stufe);
            if ($stufe && (! $beste || $stufe->rang() > $beste->rang())) {
                $beste = $stufe;
            }
        }

        return $beste;
    }

    /**
     * Welche Stufe braucht diese Anfrage? Eine Vorgabe aus dem Manifest
     * ({@see ModuleManifest::stufe()}) gewinnt, sonst gilt:
     *
     *  - GET `*.create` → verwalten, GET `*.edit` → bearbeiten, übrige GET → lesen
     *  - POST `*.store` → verwalten, übrige POST → bearbeiten
     *  - PUT/PATCH → bearbeiten
     *  - DELETE → verwalten
     */
    public static function benoetigt(string $methode, string $routeName, ?ModuleManifest $manifest = null): self
    {
        if ($vorgabe = $manifest?->stufen[$routeName] ?? null) {
            return $vorgabe;
        }

        $endet = fn (string $suffix) => str_ends_with($routeName, '.'.$suffix);

        return match (strtoupper($methode)) {
            'GET', 'HEAD', 'OPTIONS' => match (true) {
                $endet('create') => self::Verwalten,
                $endet('edit') => self::Bearbeiten,
                default => self::Lesen,
            },
            'POST' => $endet('store') ? self::Verwalten : self::Bearbeiten,
            'DELETE' => self::Verwalten,
            default => self::Bearbeiten,
        };
    }

    /**
     * Die Stufe des angemeldeten Benutzers auf der aktuellen Seite. Außerhalb
     * von Modulrouten (Core-Seiten) gibt es keine Einschränkung → verwalten.
     */
    public static function aktuell(?Request $request = null): self
    {
        $request ??= request();

        return $request->attributes->get(self::ATTRIBUT) ?? self::Verwalten;
    }

    /** Darf der angemeldete Benutzer auf der aktuellen Seite `$noetig`? */
    public static function darf(self|string $noetig): bool
    {
        return self::aktuell()->reichtFuer($noetig);
    }
}
