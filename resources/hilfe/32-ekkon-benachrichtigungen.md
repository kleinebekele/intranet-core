---
titel: Ekkon – Benachrichtigungen
route: module.ekkon.notifications.index
kategorie: Automatisierung
position: 32
rollen: admin
---

Aufgaben melden sich, wenn etwas auffällt: ein leerer Import, ein zu langer Lauf, ein neuer
Eingang. Wer diese Meldungen bekommt und auf welchem Weg, legen Sie auf dieser Seite fest. Die
Seite hat drei Reiter: **Routen**, **Teams-Channels** und **Offene Meldungen**.

## Das Prinzip: Meldungsart und Route

Jede Aufgabe gibt vor, welche **Meldungsarten** sie verschicken kann, jeweils mit einem Klartext
wie „Nächtlicher Import ohne Ergebnis". Eine Aufgabe weiß dabei nicht, wer ihre Meldung bekommt.

Das regelt eine **Route**: Eine Route ist **ein Ziel für eine Meldungsart**. Soll eine Meldung per
Teams **und** per Mail hinausgehen, legen Sie zwei Routen an. Eine Meldungsart kann beliebig viele
Ziele haben.

Die Ziele werden in dem Moment festgelegt, in dem eine Meldung entsteht. Wer eine Route später
ändert, ändert also nichts an Meldungen, die schon auf dem Weg sind.

## Eine Route anlegen

Im Reiter **Routen** steht unten der Kasten **Meldungsarten ohne Ziel** – dort stehen nur die
Meldungsarten, die noch gar kein Ziel haben, nach Modul gruppiert. Meldungsart wählen, dann:

- **Typ Mail** und unter **Mail an** eine von drei Möglichkeiten:
  - **alle System-Admins** – jeder Administrator bekommt eine eigene Mail. Wer später
    Administrator wird, bekommt die Meldungen ab dann automatisch.
  - **bestimmten Administrator** – die Adresse wird bei jeder Meldung frisch nachgeschlagen,
    folgt also einer Adressänderung.
  - **feste Adresse** – zum Beispiel ein Sammelpostfach.
- **Typ Teams** und einen **Channel** aus dem Reiter **Teams-Channels**.

Mit **Route anlegen** ist sie sofort aktiv. Für ein weiteres Ziel zu einer Meldungsart, die schon
eines hat, nehmen Sie in der Liste **weiteres Ziel anlegen** und dann **Ziel anlegen**.

Zur Auswahl stehen nur Meldungsarten, die eine Aufgabe tatsächlich verschickt. Freitext gibt es
bewusst nicht: Ein Tippfehler würde die Meldung lautlos ins Leere schicken.

## Die Liste der Routen

Die Routen stehen nach Meldungsart gruppiert. Je Ziel gibt es **deaktivieren**/**aktivieren**
und **löschen**. Eine deaktivierte Route bleibt stehen, verschickt aber nichts.

Das Abzeichen **verwaist** heißt: Keine Aufgabe verschickt diese Meldungsart mehr, etwa weil das
Modul entfernt oder umgebaut wurde. Solche Routen können Sie löschen.

## Wie die Mail aussieht

Für jede Meldungsart gibt es eine eigene Mailvorlage unter **Verwaltung → Mailvorlagen**, Titel
„Benachrichtigung: …" mit dem Klartext der Meldungsart. Ein Klick auf **Mail** in der
Routen-Liste führt direkt dorthin. Die Vorlage kennt drei Platzhalter: die Überschrift der
Meldung, ihren Text und die auslösende Aufgabe. Das Aussehen passen Sie in der Vorlage an, den
Inhalt liefert die Aufgabe.

## Zustellung und Wiederholung

Meldungen werden nicht sofort verschickt, sondern in eine Warteschlange gelegt. Eine
System-Aufgabe holt sie dort **jede Minute** ab, bis zu 25 pro Durchgang. Mails laufen danach wie
alle Mails des Intranets über den Ausgangskorb: „zugestellt" heißt bei Mail also „an den
Ausgangskorb übergeben". Ob sie wirklich hinausging, steht unter **Verwaltung → Mailversand**.

Hat eine Route kein erreichbares Ziel – etwa weil der gewählte Administrator gelöscht wurde oder
keine Adresse mehr hat –, entsteht für dieses Ziel keine Meldung. Prüfen Sie Routen auf einzelne
Personen deshalb, wenn sich im Team etwas ändert.

Scheitert die Zustellung, wird sie beim nächsten Durchgang wiederholt – insgesamt **drei
Versuche**. Danach gilt die Meldung als **failed** (nicht zugestellt) und wartet auf Sie.

Zugestellte Meldungen werden nach **14 Tagen** gelöscht, samt eventuellem Anhang.

## Offene Meldungen

Der Reiter **Offene Meldungen** zeigt die letzten 50 Meldungen, die nicht zugestellt sind:

- **pending** – wartet auf den nächsten Durchgang.
- **failed** – dreimal gescheitert. Der Grund steht unter **Letzter Fehler**.
- **ohne_ziel** – eine Aufgabe hat gemeldet, aber für diese Meldungsart gibt es keine aktive
  Route. **Niemand wurde informiert.**

Ein Klick auf den Titel zeigt die ganze Meldung mit Text, Daten und letztem Fehler. Eine
Büroklammer zeigt an, dass die Meldung einen Anhang trägt.

Oben fasst die Tabelle **Meldungsarten ohne Route** zusammen, welche Meldungsart wie oft
liegen geblieben ist. Das ist der schnellste Weg zur Ursache; der Knopf **Route anlegen** führt
direkt zum passenden Reiter.

## Erneut senden und löschen

- **erneut** (bei *failed*) legt die Meldung zurück in die Warteschlange, mit frischen drei
  Versuchen. Sinnvoll, nachdem die Ursache behoben ist.
- **nochmal senden** (bei zugestellten Meldungen, auch unter **Zuletzt versendet**) stellt eine
  Meldung ein zweites Mal zu – etwa um nach einer Änderung am Teams-Weg zu prüfen, ob sie jetzt
  richtig ankommt.
- **löschen** entfernt eine Meldung ohne Rückfrage, damit sich die Liste in Serie aufräumen lässt.

Meldungen **ohne_ziel** müssen Sie nicht von Hand nachschicken: Sobald eine aktive Route für ihre
Meldungsart existiert, werden sie beim nächsten Durchgang automatisch an die neuen Ziele
nachgereicht.

## Die Glocke

Liegen Meldungen als *failed* oder *ohne_ziel* herum, zeigt die Glocke in der Kopfzeile es jedem
Administrator an – mit Anzahl und einem Link auf diese Seite:

- *… Benachrichtigung(en) nicht zugestellt – Zustellweg prüfen*
- *… Meldung(en) ohne Route – niemand wurde informiert*

Der Hinweis verschwindet, sobald die Meldungen zugestellt, nachgereicht oder gelöscht sind.

## Die Meldung „Task lief ungewöhnlich lange"

Diese Meldungsart gehört keiner einzelnen Aufgabe, sondern dem System: Sie kommt, wenn irgendeine
Aufgabe ungewöhnlich lange läuft und deshalb angehalten wurde. Legen Sie dafür unbedingt eine
Route an – sonst merkt niemand, dass eine Aufgabe stillsteht.
