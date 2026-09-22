---
titel: Ekkon – eine Aufgabe im Detail
route: module.ekkon.task.show
kategorie: Automatisierung
position: 31
rollen: admin
---

Die Detailseite einer Aufgabe zeigt, was sie tut, wann sie läuft, wie sie eingestellt ist und
was bei den letzten Läufen passiert ist. Sie erreichen sie über den Namen der Aufgabe in der
Übersicht; zurück geht es mit **← alle Aufgaben**.

## Beschreibung, Zeitplan, nächster Lauf

- **Beschreibung** – was die Aufgabe tut, vom Modul mitgeliefert.
- **Zeitplan** – im üblichen Cron-Format mit fünf Feldern: Minute, Stunde, Tag, Monat,
  Wochentag. `*/5 * * * *` heißt „alle 5 Minuten", `0 6 * * *` „täglich um 6:00 Uhr". Der
  Zeitplan ist Teil des Moduls und lässt sich hier nicht ändern.
- Steht beim Zeitplan **steuert sich selbst**, legt die Aufgabe ihren nächsten Termin selbst
  fest. Der Zeitplan ist dann nur der Takt, in dem nachgesehen wird, ob es so weit ist.
- **Nächster Lauf** – der nächste geplante Termin, bei pausierten Aufgaben *pausiert*.

Darunter die Knöpfe **jetzt ausführen** und **⏸ geplante Läufe pausieren** bzw.
**▶ geplante Läufe aktivieren**. Pausiert ist immer nur der Zeitplan; von Hand startet die
Aufgabe weiterhin.

Zwei Hinweisbalken können oben erscheinen: *Diese Aufgabe ist pausiert* und *Das Modul … ist
deaktiviert*. Im zweiten Fall läuft die Aufgabe überhaupt nicht, auch nicht von Hand –
eingeschaltet wird das Modul unter **Verwaltung → Module**.

## Einstellungen

Manche Aufgaben bieten eine Karte **Einstellungen** an (auf breiten Bildschirmen neben den
Stammdaten, sonst darunter). Welche Felder es gibt, bestimmt
die Aufgabe selbst: Häkchen, Text- und Zahlenfelder, Auswahllisten, bei Bedarf auch eine eigene
Bedienung des Moduls unter dem Formular. Die kleine Erklärung unter einem Feld stammt ebenfalls
vom Modul.

Mit **Einstellungen speichern** gelten die Werte sofort, auch für die geplanten Läufe, und nur
für diese eine Aufgabe.

Gespeichert wird nur, was vom Standard abweicht. Stellen Sie einen Wert auf den Standard zurück,
folgt die Aufgabe wieder der Vorgabe des Moduls – auch dann, wenn das Modul diese Vorgabe mit
einer neuen Version ändert.

Häufig anzutreffen ist ein Häkchen **Probelauf**: Dann liest und berichtet die Aufgabe, schreibt
aber nichts. Das ist der sichere Weg, eine neue Aufgabe erst zu beobachten und sie scharf zu
schalten, wenn die Berichte in der Historie stimmen. Was genau „Probelauf" bei einer Aufgabe
bedeutet, steht in der Erklärung unter dem Häkchen.

## Die Lauf-Historie lesen

Jeder Lauf ist eine Zeile mit Nummer, Startzeit, Dauer in Sekunden und Nachricht. Von Hand
gestartete Läufe tragen den Zusatz *(manuell)*. Die Zeilen sind nach der Dauer eingefärbt, mit
derselben Farbskala wie in der Übersicht; gescheiterte Läufe sind rot.

In der Spalte **Nachricht** steht, was die Aufgabe über ihren Lauf berichtet hat. Ein Klick auf
**Details** klappt das vollständige Ergebnis auf (Kennzahlen des Laufs), bei den jüngsten Läufen
zusätzlich **Debug** mit technischen Einzelheiten für die Fehlersuche.

Standardmäßig zeigt die Historie nur **Läufe mit Nachricht** und alles, was nicht glatt lief.
Eine Aufgabe, die jede Minute läuft und meist nichts zu tun hat, erzeugt sonst tausende leere
Zeilen. Mit **alle Läufe zeigen** (dahinter die Zahl der stillen Läufe) sehen Sie auch diese;
**nur Läufe mit Nachricht** schaltet zurück. Pro Seite stehen 100 Läufe.

## Fehler und übersprungene Läufe

- **Fehler:** – der Lauf ist gescheitert, dahinter die Fehlermeldung. Die Übersicht färbt die
  Kachel rot, solange der letzte Lauf gescheitert ist. Der nächste geplante Lauf versucht es
  trotzdem wieder; ein einzelner Fehler (etwa ein kurz nicht erreichbares Fremdsystem) erledigt
  sich oft von selbst. Wiederholt er sich, gibt die Meldung meist den entscheidenden Hinweis.
- **grau, Dauer „–"** – der Lauf wurde übersprungen. Der Grund steht in der Nachricht:
  - *Task läuft bereits (Überlappungsschutz)* – ein früherer Lauf war noch nicht fertig.
  - *Das Modul dieses Tasks ist in der Modulverwaltung deaktiviert.*
  - *Ekkon-Tasks sind auf dieser Umgebung deaktiviert* – siehe Anleitung zur Übersicht.

Geplante Läufe einer pausierten Aufgabe hinterlassen **keinen** Eintrag – sie finden einfach
nicht statt.

## Sperren: warum ein Lauf übersprungen wird

Solange eine Aufgabe läuft, hält sie eine **Sperre**. Ein zweiter Start derselben Aufgabe –
nach Zeitplan oder von Hand – wird in dieser Zeit übersprungen. So können nie zwei Läufe
gleichzeitig dieselben Daten bearbeiten.

Die Sperre löst sich von selbst:

- **normalerweise sofort**, sobald der Lauf fertig ist – auch wenn er mit einem Fehler endet;
- **spätestens nach Ablauf ihrer Gültigkeit**, wenn der Lauf gar nicht mehr sauber endet (etwa
  weil der Server mitten im Lauf neu gestartet wurde). Die Gültigkeit beträgt in der Regel
  10 Minuten; Aufgaben mit langen Läufen setzen sie höher.

Stehen also nach einem Serverneustart ein paar Läufe als „läuft bereits" in der Historie, ist
das kein Grund zur Sorge: Nach Ablauf der Sperre läuft die Aufgabe wieder normal. Eine Sperre
von Hand zu lösen ist nicht vorgesehen.

## Zu lange Läufe: Warnung und automatische Pause

Braucht ein Lauf ungewöhnlich lange – in der Regel mehr als 10 Minuten, je Aufgabe anpassbar –,
meldet sich das System über die Meldungsart *Ein Task lief ungewöhnlich lange (System)*,
höchstens einmal pro Stunde und Aufgabe.

Zusätzlich wird die Aufgabe dabei **pausiert**, damit sie nicht über Stunden oder ein ganzes
Wochenende hinweg ein Fremdsystem belastet. Ausgenommen sind nur Aufgaben, die das System selbst
am Laufen halten, allen voran der Versand der Benachrichtigungen. Die Meldung sagt jeweils, ob
pausiert wurde.

Damit diese Warnung jemanden erreicht, braucht die Meldungsart eine Route unter
**Ekkon → Benachrichtigungen**. Wieder einschalten sollten Sie die Aufgabe erst, wenn klar ist,
warum sie so lange lief.

## Wie lange die Historie reicht

- Die **Lauf-Historie** reicht 14 Tage zurück; ältere Einträge werden nachts gelöscht.
- **Debug**-Angaben bleiben nur für die jeweils letzten 10 Läufe einer Aufgabe erhalten.

Bei einer Aufgabe, die jede Minute läuft, ist ein auffälliger Lauf also schnell aus dem Debug
verschwunden. Wer ihn untersuchen will, kopiert sich die Angaben am besten gleich heraus.
