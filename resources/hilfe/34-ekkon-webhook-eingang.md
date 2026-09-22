---
titel: Ekkon – Webhook-Eingang
route: module.ekkon.webhooks.index
kategorie: Automatisierung
position: 34
rollen: admin
---

Manche Dienste schicken von sich aus Daten, sobald bei ihnen etwas passiert – etwa ein
Paketdienst bei jeder neuen Sendungsstation. Dafür braucht der Dienst eine Adresse im Intranet,
an die er senden kann. Diese Seite stellt solche Adressen bereit und zeigt, was dort ankommt.

## Eine Quelle anlegen

Legen Sie je Absender eine **Quelle** an: **Name** (zum Beispiel der Name des Dienstes) und
optional eine **Notiz**, dann **Quelle anlegen**. Das Intranet erzeugt dazu eine eigene Adresse
mit einem langen, zufälligen Schlüssel. Mit **kopieren** landet sie in der Zwischenablage; tragen
Sie sie beim Absender ein.

Die Adresse ist ein **Passwort**: Wer sie kennt, kann Daten einliefern. Geben Sie sie nur an den
Dienst weiter, für den sie gedacht ist. Der Schlüssel lässt sich nicht nachträglich ändern – ist
eine Adresse in falsche Hände geraten, löschen Sie die Quelle und legen eine neue an.

## Quellen pflegen

- **bearbeiten** – Name und Notiz ändern. Die Adresse bleibt dieselbe.
- **deaktivieren** – die Adresse nimmt vorerst nichts mehr an; Absender bekommen eine Absage.
  Mit **aktivieren** gilt sie wieder, unverändert.
- **löschen** – entfernt die Quelle **samt allen Eingängen**. Die Adresse wird damit endgültig
  ungültig.

## Was gespeichert wird

Alles, was an einer aktiven Adresse ankommt, wird zunächst unverändert abgelegt – erst danach
entscheidet eine Aufgabe, was damit passiert. So geht nichts verloren, auch wenn die Verarbeitung
einmal klemmt.

Dabei gilt:

- Zugangsdaten und Cookies, die ein Absender mitschickt, werden **nicht** gespeichert.
- Die Absender-IP wird gekürzt abgelegt.
- Einlieferungen über 1 MB werden abgewiesen.
- Sehr viele Aufrufe in kurzer Zeit werden gedrosselt.

## Eingänge ansehen

Unter **Eingänge** gibt es je Quelle einen Reiter mit den letzten 100 Einlieferungen: Datum,
Typ, Größe und der Anfang des Inhalts. Ein Klick auf die Zeile zeigt Body und Header vollständig.

Die Spalte **Verarbeitung** zeigt, was daraus geworden ist:

- **wartet** – noch von keiner Aufgabe bearbeitet. Gibt es für diese Quelle (noch) keine Aufgabe,
  bleibt das so; die Eingänge liegen dann nur zur Ansicht hier.
- ein Text wie *verarbeitet* oder eine kurze Beschreibung – das Ergebnis, das die zuständige
  Aufgabe eingetragen hat. Die Maus darauf zeigt den Zeitpunkt.

## Erneut verarbeiten und löschen

- **erneut verarbeiten** setzt einen bereits bearbeiteten Eingang zurück auf „wartet". Die
  zuständige Aufgabe nimmt ihn bei ihrem nächsten Lauf wieder mit. Sinnvoll, wenn eine Aufgabe
  einen Eingang falsch verstanden hat und inzwischen korrigiert wurde.
- **löschen** entfernt einen einzelnen Eingang ohne Rückfrage.

Welche Aufgabe eine Quelle verarbeitet und was sie mit den Daten tut, bestimmt das jeweilige
Modul; Einzelheiten stehen in dessen Anleitung. Die Aufgaben selbst finden Sie unter
**Ekkon → Aufgaben**.
