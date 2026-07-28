---
titel: Module verwalten
route: admin.modules.index
kategorie: Verwaltung
position: 12
---

rollen: admin

Hier stehen alle installierten Module. Reihenfolge, An/Aus und die Sichtbarkeit je
Unterpunkt werden hier eingestellt – nicht im Code des Moduls.

## Reihenfolge und An/Aus

rollen: admin

Module und ihre Unterpunkte lassen sich per Ziehen sortieren; die Reihenfolge gilt sofort
für Seitenleiste und Startseite. Ein ausgeschaltetes Modul verschwindet aus der Navigation
**und** ist nicht mehr aufrufbar.

Ein Modul, dessen Paket nicht mehr installiert ist, verschwindet lautlos aus der Navigation,
bleibt in dieser Liste aber sichtbar – sonst ließe es sich weder einordnen noch entfernen.

## Rollen je Unterpunkt

rollen: admin

Modul aufklappen, dann steht neben jedem Unterpunkt, welche Rollen ihn sehen. Ohne Rolle:
nur Administratoren. Für "alle" wählen Sie ausdrücklich `user`.

Denken Sie daran nach jedem `modules:sync`: Neu hinzugekommene Unterpunkte starten ohne
Rolle und sind zunächst nur für Sie sichtbar.

## Modul entfernen

rollen: admin

Der Knopf **Modul entfernen** zeigt zuerst, was daran hängt: Menüpunkte, Rollen, sprechende
Adressen und Tabellen samt Zeilenzahl. Standardmäßig wird nur die Registrierung entfernt,
die Daten bleiben liegen.

Erst das Häkchen **mit Daten** rollt auch die Tabellen des Moduls zurück – und verlangt
dafür, dass Sie den Modul-Schlüssel abtippen. Das ist endgültig.

Die saubere Reihenfolge ist: **erst** hier entfernen, **dann** das Paket vom Server nehmen.
Andersherum fehlt der Bauplan, mit dem sich die Tabellen sauber abräumen ließen.
