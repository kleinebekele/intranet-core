# Intranet – Modulare Plattform (Core)

Ein schlankes, **modulares Intranet-Grundgerüst** auf Basis von Laravel und
[Breeze](https://laravel.com/docs/starter-kits) (Blade, Tailwind, Alpine).
Der Core bringt Anmeldung, ein responsives Layout (Header · linke Navigation ·
Inhalt · Footer) und ein **Modul-System** mit: Funktionen werden als eigenständige
Module (Composer-Pakete) nachinstalliert – der Core selbst bleibt schlank.

## Funktionen

- 🔐 Benutzer-Anmeldung. Der erste registrierte Benutzer wird automatisch Administrator.
- 🧭 Dynamische linke Navigation:
  - **Startseite** → Liste aller aktiven Module
  - **im Modul** → Modulname + „Zurück" + die Unterseiten des Moduls
- 🧩 Modul-System: jedes Modul ist ein eigenes Composer-Paket
- ⚙️ Admin-Panel: Module und Unterseiten per Drag & Drop sortieren, aktivieren/deaktivieren

## Voraussetzungen

- PHP ≥ 8.3 und [Composer](https://getcomposer.org)
- Node.js & npm (zum Bauen der Oberflächen-Assets)
- Eine Datenbank – standardmäßig **SQLite** (keine Einrichtung nötig); MySQL/MariaDB
  und PostgreSQL werden ebenso unterstützt (Verbindung in der `.env` einstellen).

## Installation

```bash
git clone <repo-url> intranet-core
cd intranet-core

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

npm install
npm run build

php artisan serve
```

Anschließend `http://127.0.0.1:8000` öffnen und den ersten Benutzer registrieren –
dieser wird automatisch Administrator.

## Module entwickeln

Der Core ist ohne Module lauffähig; Funktionen kommen als Module hinzu. Wie man ein
Modul baut, das sich automatisch integriert, beschreibt **[MODULES.md](MODULES.md)**
Schritt für Schritt (inkl. Checkliste).

Ein installierbares Beispiel-Modul ist [`do1emu/module-news`](https://packagist.org/packages/do1emu/module-news):

```bash
composer require do1emu/module-news
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

## Lizenz

MIT

---

> Hinweise zur lokalen Entwicklungsumgebung und interne Konventionen (auch für
> KI-Assistenten) stehen in **[CLAUDE.md](CLAUDE.md)**.
