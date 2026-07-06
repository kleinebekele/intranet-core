# Changelog

Alle nennenswerten Änderungen am **Core** der modularen Intranet-Plattform.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/);
Datumsangaben nach ISO (JJJJ-MM-TT). Module (z. B. `do1emu/module-news`,
`do1emu/module-userimport`) pflegen ihre Änderungen in ihren eigenen Repositories.

## [Unveröffentlicht]

### Hinzugefügt
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
