# Changelog

Alle nennenswerten Änderungen am **Core** der modularen Intranet-Plattform.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/);
Datumsangaben nach ISO (JJJJ-MM-TT). Module (z. B. `do1emu/module-news`,
`do1emu/module-userimport`) pflegen ihre Änderungen in ihren eigenen Repositories.

## [Unveröffentlicht]

### Hinzugefügt
- **Zeitzone konfigurierbar:** `APP_TIMEZONE` in der `.env` (Default weiterhin `UTC`,
  Empfehlung `Europe/Berlin`). Wirkt auf Anzeige, `now()` und den Task-Scheduler.

### Behoben
- **„Dieses Gerät merken" (2FA) überlebt jetzt Deploys:** Der serverseitige Trust-Token
  lag im App-Cache und wurde damit von `cache:clear`/`optimize:clear` (Teil jedes Deploys)
  mitgelöscht — gemerkte Geräte wurden faktisch bei jedem Deploy vergessen. Der Token liegt
  jetzt in einer eigenen Tabelle `two_factor_trusted_devices` und übersteht Cache-Leerungen.
  Sicherheitsmodell (verschlüsseltes Cookie **+** serverseitiger Abgleich) unverändert.
- **Tailwind scannt Modul-PHP:** Klassen, die Module aus PHP-Code liefern (nicht nur
  aus Blade-Views), landen jetzt im CSS-Build (`vendor/do1emu/module-*/src/**/*.php`).

### Geändert (Sicherheit)
- **Modul-Sichtbarkeit = Zugriffsrecht (Default-Deny):** Die Rollen an den Menü-Unterpunkten
  (Verwaltung → Module) steuern jetzt Navigation UND Seitenzugriff (neue Middleware
  `EnsureModuleAccess` an der `web`-Gruppe, greift für alle `module.{key}.*`-Routen).
  Neue Regel: **keine Rolle am Punkt = nur Administratoren**; „für alle" wählt man explizit
  über die Basis-Rolle `user`. Technische Routen ohne eigenen Menüpunkt sind erreichbar,
  wenn der Benutzer mindestens einen Punkt des Moduls sehen darf. Ein Modul erscheint im
  Menü, sobald ein Unterpunkt sichtbar ist (Modul-Rollen entfallen; `admins_only` bleibt
  als harte Sperre). **Bestands-Migration:** vorhandene rollenlose Punkte erhalten einmalig
  die Rolle `user`, damit sich bestehende Installationen nicht ändern.
- **Selbst-Registrierung standardmäßig geschlossen:** `/register` leitet zum Login um,
  sobald mindestens ein Benutzer existiert. `REGISTRATION_ENABLED=true` in der `.env`
  öffnet sie wieder. Ausnahme: auf frischen Installationen (0 Benutzer) bleibt sie offen,
  damit der erste Admin angelegt werden kann.

### Hinzugefügt
- **Zwei-Faktor-Authentifizierung** — immer verfügbar, **Opt-in je Benutzer** im Profil:
  Standard-Faktor ist ein 6-stelliger Code per E-Mail (10 Min gültig, max. 5 Versuche,
  Neuversand-Drossel); optional **TOTP** per Authenticator-App (z. B. Vaultwarden;
  QR-Code + Secret, Bestätigung per erstem Code) statt Mail-Codes. TOTP nach RFC 6238
  ohne externe Abhängigkeit (`App\Support\Totp`). Die Sperre hängt global an der
  `web`-Gruppe und schützt automatisch auch alle Modul-Routen.
  `.env`: **`FORCE_2FA=true`** macht 2FA für alle Benutzer verpflichtend;
  **`TWO_FACTOR_REMEMBER_DAYS`** (Standard 30, 0 = aus) steuert „Dieses Gerät merken"
  bei der Code-Abfrage (verschlüsseltes Cookie + serverseitige Gegenprüfung).
- **Admin: „TOTP zurücksetzen"** in der Benutzer-Verwaltung (z. B. bei Handy-Verlust) —
  der Benutzer fällt auf Mail-Codes zurück und kann TOTP neu einrichten.
- Feature-Tests für die komplette 2FA-Strecke (`tests/Feature/TwoFactorTest.php`).
- Weitere Icons in der `module-icon`-Whitelist (`restaurant`, `edit`, `back`, `plus`,
  `trash`, `download`, `search`, `save`, `x`, `like`, `trophy`) – für die Oberflächen der Module.

### Geändert
- Tailwind-`content` scannt jetzt auch die Blade-Views installierter Module
  (`vendor/do1emu/module-*/resources/views`), damit deren CSS-Klassen im Build landen.

## [0.2.0] - 2026-07-02

### Hinzugefügt
- **Rollen-System:** Tabellen `roles` (mit sprechendem Schlüssel `role_id`) und
  `user_roles` (n:n), Beziehung `User::roles()`.
- **Rollen-Adminpanel** (*Verwaltung → Rollen*): Rollen anlegen, umbenennen und löschen.
- **System-Rollen `admin` und `user`** (`is_system`): fest und unlöschbar. Jeder
  Benutzer erhält automatisch die Rolle `user` (auch beim Import); Bestand per
  Migration nachgetragen.
- **Aktion „Alle Zuweisungen aufheben"** für Nicht-System-Rollen (mit Warn-Modal);
  Löschen nur noch bei Rollen ohne Zuweisungen möglich.
- **Benutzer-Verwaltung** (*Verwaltung → Benutzer*): CRUD, Rollen zuweisen/entziehen,
  E-Mail unveränderbar, Willkommens-Mail für neue Benutzer, Passwort-Reset-Link.
- **Filter in der Benutzerliste:** Suche über Name/E-Mail und Filter nach Rolle.
- **Rollenbasierte Navigation:** pro Modul und pro Unterpunkt festlegen, welche
  Rollen es in der Navigation sehen. Zusätzlicher Schalter „Nur für Admins";
  Admins sehen immer alles; keine Auswahl bedeutet „für alle sichtbar".
- **Boxicons-Icon-Bibliothek** (selbst gehostet über npm/Vite), durchgängig in
  Sidebar, Header, Dashboard, Admin-Panels und Formularen.

### Geändert
- `users`-Tabelle erweitert: `externe_id`, `source` und optionales `password`
  (für Import und Einladungs-Flow).
- Komponente `module-icon` auf Boxicons umgestellt; Größe/Farbe über Utility-Klassen.
- Import-Modul `do1emu/module-userimport` auf **v1.0.1** aktualisiert (über Packagist).

## [0.1.0] - 2026-07-01

### Hinzugefügt
- **Initiales Grundgerüst** der modularen Intranet-Plattform (Laravel + Breeze):
  Modul-Vertrag mit Auto-Discovery, linke Navigation, Admin-Panel für Modul-Reihenfolge
  und An-/Abschalten, Admin-Kennzeichnung (`is_admin`, erster Benutzer wird Admin).

### Geändert
- Core-Paketname auf `do1emu/intranet-core` umgestellt.
- News-Modul wird über **Packagist** bezogen (`do1emu/module-news` `^1.0`).
- Entwickler-/KI-Wissen nach `CLAUDE.md` ausgelagert, öffentliche `README.md` neutralisiert;
  Modul-Vorlage und Lock auf PHP `^8.3` angepasst.

[Unveröffentlicht]: https://github.com/kleinebekele/intranet-core/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/kleinebekele/intranet-core/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/kleinebekele/intranet-core/releases/tag/v0.1.0
