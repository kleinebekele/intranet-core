# KI-/Entwickler-Wissen

Interne Hinweise für die Arbeit an diesem Repository – gedacht für Entwickler und
KI-Assistenten. Nichts hier ist zum Betrieb nötig; die öffentliche Kurzfassung steht
in der [README.md](README.md).

## Entwicklungsumgebung

- **Stack:** Laravel 13, Breeze (Blade + Tailwind + Alpine), SortableJS fürs Drag & Drop.
- **Datenbank:** standardmäßig SQLite (`database/database.sqlite`) – Laravel-Default, keine
  Server-Einrichtung nötig. Für Produktion auf MySQL/PostgreSQL umstellbar (`.env`).
  ⚠️ **SQLite erzwingt keine VARCHAR-Längen, MySQL schon.** Eine zu knappe Spalte fällt lokal
  nicht auf und schlägt erst auf dem Server zu – Spalten großzügig wählen und Migrationen
  idempotent halten (Spalte/Index vor dem Anlegen prüfen), damit ein zweiter Lauf nicht bricht.
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

## Testen und prüfen

```bash
php artisan test                             # ganze Suite
php artisan test --filter=ModuleAccessTest   # einzelner Test
```

Tests laufen gegen SQLite `:memory:` (`phpunit.xml`) – siehe die VARCHAR-Warnung oben: was
hier grün ist, kann auf MySQL trotzdem brechen.

Eine UI-Änderung lässt sich ohne Browser-Login prüfen, indem man im Tinker eine Anfrage durch
den Kernel schickt (`Auth::login(...)`, `Request::create(...)`, `Kernel::handle(...)`) und die
gerenderte HTML auswertet – schneller als der Umweg über die Anmeldemaske. Für Fälle, die vom
Webserver-Kontext abhängen (Dateirechte, `sys_get_temp_dir()`, PDF-Erzeugung), taugt das
allerdings nicht: dort weicht CLI vom Webserver ab.

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

## Freigabe und Deploy

Lokal hängt ein Modul als Path-Repository am Core (siehe oben), **live** zieht dieselbe
Instanz dasselbe Paket über Packagist bzw. – bei privaten Modulen – über ein VCS-Repository.
Constraint ist `^1.0`.

Daraus folgt: **Ein Tag ist die Freigabe.** `deploy.sh` führt `composer update` auf die
Modul-Pakete aus und holt damit automatisch das neueste erlaubte 1.x. Nur taggen, was live
gehen soll.

- **Versionen nie unter 1.0.** Bei `0.x` verhält sich der Caret-Operator anders (`^0.2` erlaubt
  nur `0.2.*`), das führt zu Deploys, die scheinbar nichts ziehen. Neue Module starten bei
  `1.0.0`.
- Ein **neues** Paket muss einmalig von Hand bei Packagist eingereicht werden; weitere Tags
  zieht Packagist danach selbst nach.

Deploy auf dem Server:

```bash
./deploy.sh
```

Die serverspezifischen Pfade (PHP, Composer, npm) stehen in der **nicht versionierten**
`deploy.env` (Vorlage: `deploy.env.example`). Das Skript hält an, wenn im Arbeitsverzeichnis
unerwartete Änderungen liegen – `composer.json`/`composer.lock` sind auf einer Instanz
dauerhaft geändert und deshalb ausgenommen.

⚠️ **Reihenfolge:** erst `migrate --force`, **dann** `modules:sync` – nie umgekehrt. Läuft der
Abgleich vor der Migration, löscht er Menüpunkte samt ihrer Rollenzuordnung.

⚠️ `optimize:clear` im Deploy leert auch den **Anwendungs-Cache**. Was dort als Merker liegt
(Zähler, „schon erledigt"-Marken), ist nach jedem Deploy weg – solche Zustände gehören in die
Datenbank.

Der Laravel-Scheduler braucht auf jedem Server genau **einen** Cron-Eintrag:

```
* * * * * php artisan schedule:run
```

Ohne ihn läuft keine geplante Aufgabe – und bei aktivem Mail-Ausgangskorb geht keine Mail raus.

## Nützliche Befehle

| Befehl | Zweck |
|--------|-------|
| `php artisan modules:sync` | installierte Module in die DB übernehmen |
| `php artisan modules:uninstall <key>` | Modul entfernen (`--mit-daten` rollt seine Migrationen zurück) – **vor** `composer remove` ausführen |
| `php artisan intranet:admin <email>` | Benutzer zum Administrator machen |
| `npm run build` | Assets (CSS/JS) neu bauen |
| `npm run dev` | Assets im Watch-Modus während der Entwicklung |
