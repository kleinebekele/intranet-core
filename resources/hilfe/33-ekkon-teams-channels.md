---
titel: Ekkon – Meldungen nach Microsoft Teams
kategorie: Automatisierung
position: 33
rollen: admin
---

Benachrichtigungen können statt per Mail (oder zusätzlich) in Microsoft Teams erscheinen. Ein
Ziel in Teams heißt hier **Channel**; Sie pflegen die Channels unter **Ekkon →
Benachrichtigungen**, Reiter **Teams-Channels**. Eine Route vom Typ Teams zeigt dann auf einen
dieser Channels.

Es gibt zwei Wege nach Teams. Welcher passt, hängt vom Ziel ab.

## Weg 1: Workflow (Webhook)

Der einfache Weg für einen normalen Teams-Kanal. In Teams legen Sie am Kanal einen Workflow an:
Kanal → **⋯** → **Workflows** → „Post to a channel when a webhook request is received". Teams
zeigt danach eine lange Adresse; die tragen Sie beim Anlegen des Channels unter **Webhook-URL**
ein (Weg **Workflow (Webhook)**).

Die Adresse ist ein Passwort: Wer sie kennt, kann in den Kanal schreiben. Sie wird verschlüsselt
gespeichert und nach dem Speichern nicht mehr angezeigt. Beim Bearbeiten gilt: Feld leer lassen
behält die hinterlegte Adresse, eine neue Adresse ersetzt sie.

Wichtig:

- Die alten „Incoming Webhook"-Adressen von Microsoft funktionieren nicht mehr und werden beim
  Speichern abgelehnt.
- Ein Workflow gehört dem Konto, das ihn angelegt hat. Wird dieses Konto deaktiviert, kommen
  keine Meldungen mehr an. Legen Sie den Workflow möglichst mit einem technischen Benutzer an.
- Anhänge (etwa ein Dokument zu einer Meldung) schickt das Intranet mit. Ablegen muss sie aber der
  Workflow selbst – ist er dafür nicht eingerichtet, kommt nur die Nachricht an.

## Weg 2: Graph – Chat, Kanal oder Person

Der zweite Weg postet direkt im Namen eines verbundenen Microsoft-Kontos. Er kann, was ein
Workflow nicht kann: in **Besprechungs- und Gruppenchats** schreiben, eine **Person** direkt
anschreiben und Anhänge als echte **Dateikarte** zeigen, die sich in Teams mit einem Klick öffnet.

Voraussetzungen:

1. Die Microsoft-Anmeldung des Intranets ist auf dem Server eingerichtet. Fehlt sie, steht im
   Reiter ein entsprechender Hinweis.
2. In der App-Registrierung bei Microsoft sind die Umleitungsadresse und die Berechtigungen
   eingetragen, die der Reiter vor dem ersten Verbinden nennt – mit Zustimmung eines
   Microsoft-Administrators.
3. Mit **Microsoft-Konto verbinden** melden Sie das Konto an, in dessen Namen gepostet wird.

Alle Nachrichten erscheinen unter dem Namen dieses Kontos. Bewährt hat sich ein eigenes, neutrales
Konto („Intranet" o. ä.) mit Teams-Lizenz. Es muss Mitglied in den Chats und Teams sein, in die es
schreiben soll. Anhänge legt es in seinem eigenen OneDrive im Ordner *Intranet-Anhaenge* ab und
teilt sie innerhalb der Organisation.

Beim Channel wählen Sie dann den Weg:

- **Graph → Chat/Kanal** – Feld **Chat-/Kanal-ID**. Am einfachsten über **auswählen**: Der Dialog
  *Chat oder Kanal wählen* listet alle Chats und Team-Kanäle, die das verbundene Konto sieht;
  **übernehmen** trägt die ID ein.
- **Graph → Person** – die **E-Mail-Adresse** der Person. Das Intranet eröffnet dann einen
  Einzelchat zwischen dem verbundenen Konto und dieser Person.

## Test senden

Nach dem Anlegen oder Ändern eines Channels bitte immer **Test senden** und im Teams-Kanal
nachsehen:

- Beim **Workflow** heißt die Erfolgsmeldung nur, dass Teams die Nachricht angenommen hat. Ist das
  Format falsch, meldet der Workflow trotzdem Erfolg und postet nichts. Nur der Blick in den Kanal
  beweist, dass es funktioniert.
- Beim **Graph**-Weg postet der Test eine Nachricht mit einer kleinen Textdatei. Damit sind auch
  Ablage und Dateikarte geprüft.

## Status und Pflege

In der Liste zeigt die Spalte **Weg**, wie ein Channel zustellt: *Workflow*, *Graph → Chat/Kanal*
oder *Graph → Person*. Steht dort zusätzlich *kein Konto verbunden*, kann der Channel derzeit
nicht posten.

- **deaktivieren** – der Channel nimmt vorübergehend nichts an. Meldungen an ihn scheitern mit dem
  Hinweis, dass er deaktiviert ist, und landen nach drei Versuchen als nicht zugestellt in
  **Offene Meldungen**.
- **löschen** – Routen auf diesen Channel verlieren ihr Ziel. Legen Sie sie danach neu an.

## Wenn das verbundene Konto nicht mehr kann

Ändert sich das Passwort des verbundenen Kontos oder wird der Zugriff entzogen, steht im Reiter
*Zuletzt fehlgeschlagen: … – bitte neu verbinden*. Dann mit **neu verbinden** erneut anmelden;
danach mit **erneut** in **Offene Meldungen** die liegengebliebenen Meldungen nachschicken.

**trennen** löst die Verbindung ganz. Channels auf dem Graph-Weg können bis zum erneuten Verbinden
nicht posten; Workflow-Channels sind davon nicht betroffen.
