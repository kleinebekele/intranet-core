---
titel: Sprechende Adressen (SEO)
route: admin.seo.index
kategorie: Verwaltung
position: 16
---

rollen: admin

Hier bekommen Seiten kurze, lesbare Adressen. Aus einer technischen Modul-Adresse wird so
etwas wie `/speiseplan`.

## Was dabei passiert

rollen: admin

Die neue Adresse **ersetzt** die alte – auch in allen internen Links, die das Intranet
selbst erzeugt. Die alte Adresse leitet weiter, damit gespeicherte Lesezeichen nicht ins
Leere laufen.

## Unterpfade sind relativ

rollen: admin

Die Adresse eines Unterpunkts wird an die des Modul-Stamms angehängt. Heißt der Stamm
`speiseplan` und ein Unterpunkt `bestellungen`, ergibt das `/speiseplan/bestellungen`.
Ändern Sie später den Stamm, wandern alle Unterpunkte automatisch mit.

Soll ein Eintrag stattdessen eine vollständige Adresse sein, setzen Sie das Häkchen
**Absoluter Pfad**.

## Kollisionen

rollen: admin

Zwei Seiten dürfen nicht auf derselben fertigen Adresse landen. Geprüft wird die
**zusammengesetzte** Adresse, nicht der eingetippte Teil – zwei Unterpunkte in
verschiedenen Modulen dürfen also gleich heißen.
