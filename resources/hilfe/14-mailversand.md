---
titel: Mailversand und Ausgangskorb
route: admin.mail.index
kategorie: Verwaltung
position: 14
---

rollen: admin

Das Intranet verschickt Mails nicht sofort, sondern legt sie in einen Ausgangskorb. Ein
Hintergrundlauf holt sie dort im erlaubten Takt ab. Diese Seite ist beides zugleich:
Warteschlange und Versand-Protokoll.

## Warum verzögert

rollen: admin

Zwei Gründe, eine Lösung: Der Korb hält die Anzahl der Mails pro Stunde ein, die der
Postausgang verträgt – und er protokolliert nebenbei lückenlos, was wann an wen ging.

Eilige Mails haben Vorfahrt: Anmelde-Codes und Passwort-Links gehen an der Warteschlange
vorbei. Es wäre unzumutbar, auf einen Anmeldecode zu warten, weil vorher ein Rundschreiben
abgearbeitet wird.

## Das Stundenlimit

rollen: admin

Das Limit steht unter **Verwaltung → Einstellungen**, nicht in einer Datei auf dem Server.
Der Wert **0 bedeutet: kein Limit**. Gezählt wird gleitend über die letzten 60 Minuten.

## Wenn nichts rausgeht

rollen: admin

Der häufigste Fall: Auf dem Server läuft der regelmäßige Hintergrundlauf nicht. Ist der
Ausgangskorb eingeschaltet, geht ohne ihn **gar keine** Mail raus – sie sammeln sich hier
sichtbar an. Das ist der erste Ort zum Nachsehen, wenn jemand meldet, er bekomme keine Mail.

Zweiter Fall: Adressen mit einer nicht zustellbaren Endung, etwa aus einem internen
Verzeichnis. Solche Empfänger werden vor dem Versand aussortiert – eine Mail an mehrere
Empfänger geht dann an die übrigen raus, statt komplett zu scheitern.

## Erneut senden

rollen: admin

Zu jeder gescheiterten Mail gibt es einen Knopf, der sie zurück in die Warteschlange legt.
Sinnvoll, nachdem die Ursache behoben ist – ein zweiter Versuch bei falscher Adresse
scheitert genauso wie der erste.
