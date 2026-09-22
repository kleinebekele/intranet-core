---
titel: Ekkon – Aufgaben im Überblick
route: module.ekkon.index
kategorie: Automatisierung
position: 30
rollen: admin
---

Ekkon ist der Teil des Intranets, der wiederkehrende Arbeit im Hintergrund erledigt: Daten
abgleichen, Meldungen verschicken, Eingänge verarbeiten. Jede dieser Arbeiten ist eine
**Aufgabe** (im Programm auch „Task" genannt). Diese Seite zeigt alle Aufgaben mit ihrem
Zustand. Sie ist nur für Administratoren erreichbar – unabhängig davon, was in der
Modulverwaltung eingestellt ist.

## Woher die Aufgaben kommen

Aufgaben werden nicht hier angelegt, sondern von den Modulen mitgebracht. Ein Modul, das etwas
regelmäßig tun muss, liefert seine Aufgaben gleich mit – samt Zeitplan und Beschreibung. Die
Gruppe **System** gehört dem Intranet selbst; darin stehen unter anderem der Versand der
Benachrichtigungen und zwei Selbsttests (`Demo/Ping`, `Mssql/Ping`).

Jede Aufgabe hat einen Namen der Form **Gruppe/Name**. Unter diesem Namen stehen ihre
Lauf-Historie, ihre Einstellungen und ihr Pause-Schalter.

## Aufbau der Seite

Je Modul gibt es eine Karte, darin die Aufgaben nach Kategorie. Ein Klick auf den Modulkopf
klappt die Karte auf und zu. Reihenfolge: zuerst **System**, dann die eingeschalteten Module,
danach ausgeschaltete Module (eingeklappt, mit dem Hinweis *Modul deaktiviert – Tasks laufen
nicht*), ganz am Ende **Ohne Modul** (Abzeichen *kein Modul angegeben*) für Aufgaben, die sich
keinem Modul zuordnen lassen – sie laufen immer.

Jede Kachel zeigt:

- **letzter:** – wann die Aufgabe zuletzt gelaufen ist.
- **nächster:** – wann sie laut Zeitplan wieder dran ist. Bei pausierten Aufgaben steht hier „–".
- **Anzahl:** – wie viele Läufe in der Historie stehen, dahinter der Speicherplatz, den diese
  Einträge belegen. So fällt auf, welche Aufgabe die Historie vollschreibt.
- **⌀-Dauer:** – mittlere Laufzeit in Sekunden, in Klammern die kürzeste und die längste. Die
  Farbe reicht von Türkis (sehr schnell) über Grün, Gelb und Orange bis Rot und Schwarz (sehr
  langsam).

Ein Klick auf den Namen öffnet die Detailseite mit Beschreibung, Einstellungen und
Lauf-Historie.

## Farben und Abzeichen

- **Rot mit „Fehler"** – der letzte Lauf ist gescheitert. Die Fehlermeldung steht in der
  Lauf-Historie auf der Detailseite.
- **Bernstein mit „pausiert"** – die Aufgabe läuft nicht nach Zeitplan. Pausierte Aufgaben
  stehen am Ende ihrer Kategorie, damit auf einen Blick klar ist, was arbeitet und was ruht.
  Pausiert schlägt Fehler: Eine ruhende Aufgabe wird bernsteinfarben gezeigt, das
  Fehler-Abzeichen bleibt aber stehen.

## Jetzt ausführen

Der Knopf **jetzt ausführen** startet die Aufgabe sofort von Hand. Die Seite wartet, bis der
Lauf fertig ist, und zeigt danach auf der Detailseite das **Ergebnis des manuellen Laufs**.
Bei langen Aufgaben kann das dauern – bitte nicht mehrfach klicken; ein zweiter Start während
eines laufenden wird ohnehin übersprungen.

„Jetzt ausführen" funktioniert auch bei pausierten Aufgaben. Es ist also der richtige Weg, eine
Aufgabe nach einer Änderung einmal gezielt zu prüfen, ohne den Zeitplan wieder einzuschalten.

## Pausieren und fortsetzen

Der kleine Knopf neben „jetzt ausführen" schaltet die **geplanten Läufe** aus (⏸) und wieder
ein (▶). Pausiert ist nur der Zeitplan: Von Hand lässt sich die Aufgabe weiterhin starten.
Einstellungen und Historie bleiben beim Pausieren erhalten.

Aufgaben können auch **von selbst** pausiert werden: Läuft eine Aufgabe ungewöhnlich lange,
hält das System sie an und schickt eine Benachrichtigung. Mehr dazu in der Anleitung zur
Detailseite. Wieder einschalten sollten Sie sie erst, wenn die Ursache klar ist.

## Aufgaben eines ausgeschalteten Moduls

Ist ein Modul unter **Verwaltung → Module** ausgeschaltet, läuft keine seiner Aufgaben – weder
nach Zeitplan noch über „jetzt ausführen". Ein Versuch von Hand wird in der Historie als
übersprungen vermerkt, mit Begründung. Pausen, Einstellungen und Historie bleiben erhalten und
gelten wieder, sobald das Modul eingeschaltet wird.

## Gelber Hinweis: Tasks sind deaktiviert

Steht oben *Ekkon-Tasks sind auf dieser Umgebung deaktiviert*, fehlt auf dem Server die
Freigabe `EKKON_TASKS_ENABLED=true` in der `.env`. Dann läuft **gar nichts** – kein Zeitplan,
kein „jetzt ausführen". Das ist Absicht für Test- und Entwicklungsumgebungen, damit dort nie
versehentlich ein echtes Fremdsystem angefasst wird. Auf dem echten Server muss die Freigabe
gesetzt sein.

Zweite Voraussetzung auf dem Server ist der regelmäßige Hintergrundlauf, der jede Minute
nachsieht, welche Aufgabe dran ist. Fehlt er, stehen die Zeiten unter „letzter:" still, obwohl
nichts pausiert ist.

## Roter Hinweis: doppelt vergebene Namen

Zwei Aufgaben aus verschiedenen Modulen dürfen nicht denselben Namen tragen, sonst würden sie
sich Historie und Pause-Schalter teilen. Passiert es doch, gilt die zuerst gefundene; die
andere läuft **nicht** und wird oben rot aufgeführt. Das lässt sich nur im Modul selbst beheben
– geben Sie den Hinweis an den Entwickler des Moduls weiter.
