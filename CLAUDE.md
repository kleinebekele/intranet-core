# KI-/Entwickler-Wissen

Interne Hinweise für die Arbeit an diesem Repository – gedacht für Entwickler und
KI-Assistenten. Nichts hier ist zum Betrieb nötig; die öffentliche Kurzfassung steht
in der [README.md](README.md).

## Entwicklungsumgebung

- **Stack:** Laravel 13, Breeze (Blade + Tailwind + Alpine), SortableJS fürs Drag & Drop.
- **Datenbank:** standardmäßig SQLite (`database/database.sqlite`) – Laravel-Default, keine
  Server-Einrichtung nötig. Für Produktion auf MySQL/PostgreSQL umstellbar (`.env`).
- **PHP/Node über [Laravel Herd](https://herd.laravel.com) (Windows):** Die Binaries liegen
  unter `~/.config/herd/bin` und sind in Terminals/Tool-Shells oft **nicht im PATH**.
  Vor `php`/`composer`/`npm` den Ordner voranstellen, z. B.:
  ```powershell
  $env:PATH = "$HOME\.config\herd\bin;$HOME\.config\herd\bin\nvm\<node-version>;$env:PATH"
  ```
  Composer notfalls über `php <herd-bin>\composer.phar` aufrufen.

## Lokal starten

```bash
php artisan serve   # http://127.0.0.1:8000
```

Ersten Benutzer über `/register` anlegen – der **erste** Benutzer wird automatisch Admin
(siehe `User::booted()`). Weitere Admins: `php artisan intranet:admin <email>`.

## Architektur-Kern & Stolpersteine

- **Modul-Vertrag** in `app/Modules/Support/`:
  - `ModuleServiceProvider` (abstrakt) – Module erben davon und liefern ein `ModuleManifest`.
    Lädt Routen/Views/Migrationen automatisch relativ zur Provider-Datei (**muss in `src/` liegen**).
  - `ModuleRegistry` – Singleton, sammelt alle Manifeste ein.
  - `Navigation` – baut die Sidebar-Daten (und über `dashboard.blade.php` auch die
    Dashboard-Kacheln); zeigt nur Module, die **in der DB aktiv UND im Registry vorhanden**
    sind (deinstallierte Pakete verschwinden lautlos) **und mindestens einen für den
    Benutzer sichtbaren Unterpunkt** haben. Die Modulverwaltung fragt `Module` direkt ab und
    zeigt deshalb auch leere Module.
- ⚠️ **Provider-Reihenfolge:** Paket-(Modul-)Provider registrieren **vor** dem
  `AppServiceProvider`. Deshalb wird die Registry mit `singletonIf` gebunden (in beiden),
  sonst überschreibt der Core die bereits gefüllte Registry mit einer leeren.
- ⚠️ **Positionen sind 0-basiert.** Nicht `position ?: fallback` verwenden – die 0 ist gültig
  und würde sonst als „leer" behandelt (führte zu kollidierenden Positionen).
- **Routen-Konvention:** `module.{key}.*` mit Präfix `modules/{key}` und `web`+`auth`-Middleware.
  Daran erkennt `ModuleRegistry::currentKey()` den Modul-Kontext für die Sidebar.
- **`modules:sync`** übernimmt installierte Module in `modules` / `module_menu_items` und
  **behält** dabei die im Admin gesetzte Reihenfolge und den An/Aus-Status bestehender Einträge.

## Module lokal entwickeln

Während der Entwicklung wird ein Modul als **Path-Repository** eingebunden (Modul-Ordner liegt
neben dem Core). In der `composer.json` des Core:

```jsonc
"repositories": [ { "type": "path", "url": "../<modul-ordner>" } ]
```

Danach `composer require <vendor>/<paket>:*`, `php artisan modules:sync`, `php artisan migrate`.
Für die Veröffentlichung eines Moduls: siehe [MODULES.md](MODULES.md) (Packagist/VCS).

## Nützliche Befehle

| Befehl | Zweck |
|--------|-------|
| `php artisan modules:sync` | installierte Module in die DB übernehmen |
| `php artisan modules:uninstall <key>` | Modul entfernen (`--mit-daten` rollt seine Migrationen zurück) – **vor** `composer remove` ausführen |
| `php artisan intranet:admin <email>` | Benutzer zum Administrator machen |
| `npm run build` | Assets (CSS/JS) neu bauen |
| `npm run dev` | Assets im Watch-Modus während der Entwicklung |
