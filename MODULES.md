# Module für das Intranet bauen

Diese Anleitung beschreibt, wie ein **Modul** (Plugin) aufgebaut sein muss, damit es
sich sauber in die Intranet-Plattform einfügt: eigene Seiten, ein Eintrag in der linken
Navigation und – sobald installiert – sortier- und abschaltbar im Admin-Panel.

> **Grundprinzip:** Jedes Modul ist ein eigenständiges **Composer-Paket in einem eigenen
> Git-Repository**. Der Core (dieses Repo) bleibt unverändert; Module werden per
> `composer require` installiert. Als vollständiges Beispiel dient das Repo
> [`intranet-module-news`](../intranet-module-news).

---

## 1. Was ein Modul dem Core mitteilt

Ein Modul liefert genau eine kleine Anmelde-Klasse – seinen **ServiceProvider** –, die von
`App\Modules\Support\ModuleServiceProvider` erbt und ein **Manifest** zurückgibt:

```php
namespace Intranet\Modules\News;

use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;

class NewsServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make(
                key:  'news',            // eindeutiger Schlüssel (klein, ohne Leerzeichen)
                name: 'Neuigkeiten',     // Anzeigename im Menü
                icon: 'newspaper',       // Icon-Name (siehe Abschnitt 6)
            )
            ->item('index',  'Übersicht',      'module.news.index')
            ->item('create', 'Beitrag anlegen', 'module.news.create');
    }
}
```

Mehr ist für die Integration **nicht** nötig. Routen, Views und Migrationen lädt die
Basisklasse automatisch anhand der Ordnerstruktur (siehe Abschnitt 3).

---

## 2. Pflicht-Ordnerstruktur

```
mein-modul/
├── composer.json                 # Paketname + Provider-Auto-Discovery (Abschnitt 4)
├── src/
│   └── XxxServiceProvider.php     # MUSS in src/ liegen (wichtig, siehe unten)
├── routes/
│   └── web.php                    # Routen des Moduls
├── resources/
│   └── views/                     # Blade-Views des Moduls
└── database/
    └── migrations/                # eigene Tabellen (optional)
```

> ⚠️ **Der ServiceProvider muss direkt in `src/` liegen.** Die Basisklasse ermittelt das
> Paketverzeichnis relativ zu dieser Datei (zwei Ebenen höher). Liegt der Provider woanders,
> werden Routen/Views/Migrationen nicht gefunden.

---

## 3. Konventionen (unbedingt einhalten)

Damit die Navigation den Modul-Kontext korrekt erkennt, gelten feste Namensregeln:

| Bereich          | Regel                                   | Beispiel (key = `news`)      |
|------------------|-----------------------------------------|------------------------------|
| **URL-Präfix**   | `modules/{key}`                         | `modules/news`               |
| **Routen-Namen** | `module.{key}.*`                        | `module.news.index`          |
| **Landing-Page** | erste Menü-Position                      | `module.news.index`          |
| **View-Namespace** | `{key}::view`                         | `view('news::index')`        |
| **Middleware**   | mindestens `web`, meist zusätzlich `auth`| `->middleware(['web','auth'])` |

`routes/web.php` sieht damit so aus:

```php
use Illuminate\Support\Facades\Route;
use Intranet\Modules\News\Http\Controllers\NewsController;

Route::middleware(['web', 'auth'])
    ->prefix('modules/news')
    ->name('module.news.')
    ->group(function () {
        Route::get('/',        [NewsController::class, 'index'])->name('index');
        Route::get('/anlegen', [NewsController::class, 'create'])->name('create');
        Route::post('/',       [NewsController::class, 'store'])->name('store');
    });
```

> **Warum `web`?** Modul-Routen werden außerhalb der Standard-Route-Gruppe geladen. Ohne
> `web`-Middleware gäbe es keine Session, kein CSRF und keinen eingeloggten Benutzer.

Die Menü-Punkte im Manifest müssen exakt auf diese Routen-Namen zeigen
(`module.news.index`, `module.news.create`).

---

## 4. `composer.json` des Moduls

```json
{
    "name": "do1emu/module-news",
    "type": "library",
    "require": { "php": "^8.3" },
    "autoload": {
        "psr-4": { "Intranet\\Modules\\News\\": "src/" }
    },
    "extra": {
        "laravel": {
            "providers": [ "Intranet\\Modules\\News\\NewsServiceProvider" ]
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Der Eintrag unter `extra.laravel.providers` sorgt dafür, dass Laravel den Provider beim
Installieren **automatisch findet** (Package Auto-Discovery) – du musst ihn nirgends von Hand
registrieren.

---

## 5. Views, Controller, Models

- **Views** liegen in `resources/views/` und werden über den Namespace angesprochen:
  `view('news::index')`. Für das gemeinsame Layout einfach `<x-app-layout>` verwenden –
  Header, linke Navigation und Footer kommen dann automatisch vom Core.
- **Controller** brauchen keine besondere Basisklasse. `$request->validate([...])` genügt
  für Formularprüfungen.
- **Models/Migrationen** gehören dem Modul. Nutze einen eigenen Tabellennamen mit klarem
  Präfix (z. B. `news_posts`), um Kollisionen zu vermeiden.

---

## 6. Icons

Das Icon im Manifest ist ein Name aus dem eingebauten Satz (Komponente
`resources/views/components/module-icon.blade.php`):

`home`, `newspaper`, `users`, `cog`, `folder`, `chart`, `calendar`, `document`, `chat`.

Unbekannte Namen fallen automatisch auf ein neutrales Standard-Icon zurück. Möchtest du ein
neues Icon, ergänze einfach den `$icons`-Array in der Komponente.

---

## 7. Modul installieren (Entwicklungs-Workflow)

Solange ein Modul lokal (noch nicht auf einem Git-Server) liegt, wird es über ein
**Path-Repository** eingebunden. In der `composer.json` des **Core** hinzufügen:

```json
"repositories": [
    { "type": "path", "url": "../intranet-module-news" }
]
```

Danach im Core-Verzeichnis:

```bash
composer require do1emu/module-news:*   # Modul installieren
php artisan modules:sync                   # Modul in die Datenbank übernehmen
php artisan migrate                        # Modul-Tabellen anlegen
```

Liegt das Modul später in einem echten Git-Repo, ersetzt du das Path- durch ein
VCS-Repository:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/dein-account/intranet-module-news" }
]
```

---

## 8. Was `php artisan modules:sync` macht

- schreibt jedes installierte Modul und seine Unterseiten in die Tabellen
  `modules` bzw. `module_menu_items`,
- **behält** dabei die im Admin-Panel eingestellte Reihenfolge und den An/Aus-Status
  bestehender Module bei,
- fügt neu hinzugekommene Unterseiten hinten an und entfernt gelöschte.

Führe den Befehl nach **jeder** Installation oder Aktualisierung eines Moduls aus.

---

## 8a. Ein Modul wieder entfernen: `php artisan modules:uninstall <key>`

Das Gegenstück zu `modules:sync`. Es zeigt zuerst, was am Modul hängt (Menüpunkte,
Rollen, sprechende Adressen, gelaufene Migrationen samt Tabellen und Zeilenzahlen)
und fragt dann nach.

Dasselbe gibt es als Knopf im Backend: **Verwaltung → Module**, Modul aufklappen,
unten *„Modul entfernen"*. Das Löschen der Tabellen verlangt dort zusätzlich, den
Modul-Schlüssel abzutippen. Nur `composer remove` bleibt Sache der Konsole.

```bash
php artisan modules:uninstall userimport            # nur die Registrierung
php artisan modules:uninstall userimport --mit-daten # zusätzlich Tabellen zurückrollen
php artisan modules:uninstall userimport --dry-run   # nur anschauen
```

Ohne `--mit-daten` bleiben die Tabellen des Moduls stehen – Registrierung weg,
Daten sicher. Erst `--mit-daten` entfernt auch die Tabellen **dieses** Moduls.

Wie das geschieht, hängt davon ab, was noch da ist:

| Lage | Weg |
|---|---|
| Paket noch installiert | das echte `down()` der Migration, neueste zuerst |
| Paket schon weg | die aufgezeichneten Tabellen werden direkt verworfen |

Möglich macht das die Tabelle `module_migrations`: Bei **jedem `modules:sync`**
merkt sich der Core, welche Migration zu welchem Modul gehört und welche
Tabellen sie anlegt. Diese Aufzeichnung überlebt das Paket – deshalb lässt sich
ein Modul auch dann noch vollständig entfernen, wenn `composer remove` längst
gelaufen ist.

> **Grenze:** Migrationen, die nur bestehende Tabellen *ändern*, lassen sich ohne
> ihr `down()` nicht rückgängig machen – ohne Paket verschwindet nur ihr Eintrag
> aus `migrations`. Wo es darauf ankommt, also weiterhin lieber erst
> deinstallieren, dann `composer remove`.
>
> Module, die schon vor der Einführung von `module_migrations` verwaist waren,
> haben keine Aufzeichnung; deren Tabellen muss man von Hand abräumen.

`migrate:rollback --path` kommt für beides nicht in Frage: Das rollt immer den
letzten *Stapel* zurück, und darin stecken typischerweise Migrationen ganz
anderer Pakete.

---

## 9. Checkliste für ein neues Modul

- [ ] Eigenes Repo mit der Ordnerstruktur aus Abschnitt 2
- [ ] `composer.json` mit `psr-4`-Autoload und `extra.laravel.providers`
- [ ] Provider in `src/`, erbt von `ModuleServiceProvider`, liefert ein `manifest()`
- [ ] Routen als `module.{key}.*` mit Präfix `modules/{key}` und `web`-Middleware
- [ ] Menü-Punkte im Manifest zeigen auf existierende Routen-Namen
- [ ] Views nutzen `<x-app-layout>`
- [ ] Im Core einbinden (`repositories` → `composer require`)
- [ ] `php artisan modules:sync` und `php artisan migrate` ausführen
- [ ] Reihenfolge/Sichtbarkeit im Admin-Panel unter **Verwaltung** prüfen

Fertig – das Modul erscheint in der linken Navigation und ist sortierbar. 🎉
