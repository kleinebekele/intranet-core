---
titel: Ekkon – Teams-Chat und KI-Antworten
route: module.ekkon.teams.index
kategorie: Automatisierung
position: 35
rollen: admin
---

Das Microsoft-Konto, das unter **Ekkon → Benachrichtigungen** für den Graph-Weg verbunden ist,
kann nicht nur schreiben, sondern auch lesen: Was andere diesem Konto in Teams schreiben, landet
auf dieser Seite. Von hier aus lässt sich von Hand antworten – oder eine KI antwortet automatisch.

## Voraussetzungen

- Ein Microsoft-Konto ist verbunden (siehe Anleitung „Meldungen nach Microsoft Teams"). Ohne
  Konto steht unter **Konto** *nicht verbunden*.
- Auf dem Server läuft der **Lauscher** als Dauerdienst. Er fragt alle paar Sekunden bei Teams
  nach, ob etwas Neues da ist. Ohne ihn bleibt diese Seite leer.

Der Kasten **Eingang** zeigt das Konto, die **Letzte Bewegung** und die Zahl der **Chats im
Blick**. „Letzte Bewegung" ist nur ein grober Hinweis: Sie ändert sich nur, wenn in einem Chat
tatsächlich etwas passiert ist. Ein alter Zeitpunkt heißt also nicht zwingend, dass der Lauscher
steht – vielleicht hat einfach niemand geschrieben. Zum Prüfen schreiben Sie dem Konto selbst eine
kurze Nachricht.

## Welche Nachrichten ankommen

Abgeholt werden Nachrichten aus allen Chats, in denen das Konto Mitglied ist – Einzelchats,
Gruppen- und Besprechungschats. Übersprungen werden die eigenen Nachrichten des Kontos und
Systemnachrichten von Teams. Sieht der Lauscher einen Chat zum ersten Mal, holt er nur die
Nachrichten der letzten 10 Minuten, nicht den ganzen Verlauf.

## Die Nachrichtenliste

Unter **Nachrichten** stehen die letzten 100 mit Zeitpunkt, Chat, Absender und Anfang des Texts.
Ein Klick auf die Zeile zeigt die ganze Nachricht, Anhänge als Links und – falls vorhanden – die
gegebene Antwort.

Die Spalte **Verarbeitung**:

- **offen** – noch nicht beantwortet.
- **Fehler** – die automatische Antwort ist gescheitert; die Maus darauf zeigt den Grund.
- ein Text – was passiert ist, etwa welches KI-Modell geantwortet hat, *von Hand beantwortet*
  mit Namen, oder warum bewusst nicht geantwortet wurde.

**antworten** öffnet ein Textfeld; **Antwort senden** postet die Antwort im selben Chat, im Namen
des verbundenen Kontos. **löschen** entfernt die Nachricht nur hier im Intranet, nicht in Teams.

Unten listet **Chats im Blick** alle Chats, die der Lauscher verfolgt, und bis wann gelesen wurde.

## KI-Antworten einrichten

Im Abschnitt **KI-Antworten** verbinden Sie eine KI, die eingehende Nachrichten automatisch
beantwortet. Angebunden wird ein Anbieter mit OpenAI-kompatibler Schnittstelle.

- **API-Adresse** – die Adresse der Schnittstelle des Anbieters.
- **API-Schlüssel** – aus dem Kundenbereich des Anbieters. Er wird verschlüsselt gespeichert und
  nie wieder angezeigt; Feld leer lassen behält den hinterlegten Schlüssel.
- **Modell** – mit **Modelle laden** holt die Seite die Liste des Anbieters; ein Klick ins Feld
  zeigt dann die Auswahl.
- **KI antwortet automatisch** – der Hauptschalter.
- **in Gruppen- und Besprechungschats nur bei @-Erwähnung des Bots** – in Einzelchats antwortet
  die KI immer, in Gruppen mit diesem Häkchen nur, wenn das Konto ausdrücklich erwähnt wird.
- **Systemprompt** – Rolle und Regeln für die KI, in eigenen Worten. Leer gelassen, gilt eine
  Vorgabe.

Nach **Speichern** zeigt das Abzeichen neben der Überschrift den Stand: *antwortet* mit dem
Modell, *eingeschaltet, aber unvollständig* (Schlüssel oder Modell fehlt) oder *aus*.

Mit **Probefrage an die KI** prüfen Sie die Verbindung. Die Antwort erscheint oben auf der Seite;
nach Teams wird dabei nichts gepostet.

## So antwortet die KI

Kommt eine Nachricht an und die KI ist eingeschaltet, setzt das Konto zunächst ein 👀 unter die
Nachricht und postet einen Platzhalter „…". Sobald die Antwort da ist, ersetzt sie den Platzhalter
an derselben Stelle.

Die KI bekommt dabei nicht nur die neue Nachricht, sondern auch die vorangegangenen Nachrichten
dieses Chats samt ihrer eigenen Antworten – sie kennt also den Gesprächsverlauf. Bedenken Sie:
Alles, was dem Konto geschrieben wird, geht damit an den KI-Anbieter.

Nicht beantwortet werden Nachrichten ohne Text (nur Bild oder Anhang) und, bei gesetztem Häkchen,
Gruppennachrichten ohne @-Erwähnung. Kann die KI nicht antworten, steht im Chat eine kurze
Entschuldigung und hier in der Liste **Fehler** mit dem Grund.
