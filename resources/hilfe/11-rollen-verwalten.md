---
titel: Rollen verstehen und vergeben
route: admin.roles.index
kategorie: Verwaltung
position: 11
---

rollen: admin

Rollen sind der einzige Hebel, mit dem gesteuert wird, wer was sieht. Sie hängen nicht an
einzelnen Personen, sondern an Aufgaben – "Lehrer", "Verwaltung", "Lager".

## Zwei Arten von Rollen

rollen: admin

- **System-Rollen** (`admin`, `user`) bringt die Plattform mit. Sie lassen sich nicht
  löschen, weil Grundfunktionen darauf aufbauen. Jeder Benutzer bekommt beim Anlegen
  automatisch die Rolle `user`.
- **Eigene Rollen** legen Sie hier an, so viele Sie brauchen. Module dürfen ebenfalls Rollen
  mitbringen; die verschwinden wieder, wenn das Modul mit `--mit-daten` entfernt wird.

## Wo Rollen wirken

rollen: admin

An drei Stellen:

1. **Verwaltung → Module**: je Modul und je Unterpunkt, wer ihn im Menü sieht – und damit
   auch, wer die Seite überhaupt aufrufen darf. Beides ist dieselbe Einstellung, es gibt
   keinen Menüpunkt, der zwar unsichtbar, aber erreichbar wäre.
2. **Im Wiki**: je Absatz einer Seite. So steht in einer gemeinsamen Anleitung der
   Verwaltungsteil nur bei denen, die ihn brauchen.
3. **In einzelnen Modulen**, wo es fachlich nötig ist.

## Der sichere Standard

rollen: admin

Ein Menüpunkt **ohne** zugewiesene Rolle ist nur für Administratoren sichtbar. Wer eine
Seite für alle öffnen will, wählt dort ausdrücklich die Rolle `user` aus.

Das ist bewusst herum: Ein vergessenes Häkchen führt so dazu, dass zu wenige eine Seite
sehen – nicht zu viele.

## Rolle entfernen

rollen: admin

Beim Löschen einer Rolle verlieren alle Benutzer diese Rolle, und jede Sichtbarkeitsregel,
die sich darauf stützte, fällt weg. Prüfen Sie vorher, an welchen Menüpunkten sie hängt,
sonst steht hinterher eine Seite ohne Rolle da – und ist damit nur noch für Administratoren
sichtbar.
