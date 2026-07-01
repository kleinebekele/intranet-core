# Intranet – Core (Grundgerüst)

Modulare Intranet-Plattform auf Basis von **Laravel 13** + **Breeze** (Blade, Tailwind, Alpine).
Der Core bringt Login, das Layout (Header · linke Navigation · Inhalt · Footer) und ein
**Modul-System** mit: Funktionen werden als eigenständige Module (Plugins) per Composer
nachinstalliert.

## Funktionen

- 🔐 Benutzer-Anmeldung (Breeze). Der erste registrierte Benutzer wird automatisch Administrator.
- 🧭 Dynamische linke Navigation:
  - **Startseite** → Liste aller aktiven Module
  - **im Modul** → Modulname + „Zurück" + die Unterseiten des Moduls
- 🧩 Modul-System: jedes Modul ist ein eigenes Composer-Paket / Git-Repo
- ⚙️ Admin-Panel („Verwaltung"): Module und Unterseiten per Drag & Drop sortieren, Module an/aus

## Einrichtung

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Dann `http://127.0.0.1:8000` öffnen und den ersten Benutzer registrieren (= Admin).

> **Hinweis (Herd/Windows):** PHP, Composer und Node liegen unter `~/.config/herd/bin`
> und sind in Tool-Shells ggf. nicht im PATH. Voranstellen:
> `~/.config/herd/bin` (+ `nvm/<version>` für Node).

## Module entwickeln

Wie man ein Modul baut, das sich korrekt integriert, steht ausführlich in **[MODULES.md](MODULES.md)**.
Als vollständiges Beispiel dient das Repo [`intranet-module-news`](../intranet-module-news).

Kurzfassung – Modul installieren:

```bash
# in composer.json des Core einen repositories-Eintrag auf das Modul-Repo, dann:
composer require do1emu/module-news:*
php artisan modules:sync   # Modul in die DB übernehmen (Reihenfolge/An-Aus bleiben erhalten)
php artisan migrate        # ggf. Modul-Tabellen anlegen
```

## Nützliche Befehle

| Befehl | Zweck |
|--------|-------|
| `php artisan modules:sync` | installierte Module in die Datenbank übernehmen |
| `php artisan intranet:admin <email>` | einen Benutzer zum Administrator machen |

## Architektur (Kurzüberblick)

| Baustein | Ort |
|----------|-----|
| Modul-Vertrag (Basisklassen) | `app/Modules/Support/` |
| Datenbank-Modelle | `app/Models/Module.php`, `ModuleMenuItem.php` |
| Navigation (Sidebar-Logik) | `app/Modules/Support/Navigation.php` + `app/View/Composers/` |
| Admin-Panel | `app/Http/Controllers/Admin/ModuleController.php`, `resources/views/admin/` |
| Layout | `resources/views/layouts/` (app, header, sidebar, footer) |
