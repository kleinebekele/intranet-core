---
titel: Mailvorlagen bearbeiten
route: admin.mailvorlagen.index
kategorie: Verwaltung
position: 13
---

rollen: admin

Jede Mail, die das Intranet verschickt, hat hier eine Vorlage: Betreff, HTML-Fassung und
Klartext-Fassung. Module dürfen eigene Vorlagen anmelden, sie tauchen dann automatisch in
dieser Liste auf.

## Was gespeichert wird

rollen: admin

Gespeichert wird nur, was vom mitgelieferten Standard **abweicht**. Solange Sie eine Vorlage
nicht anfassen, zieht sie Verbesserungen aus dem nächsten Update automatisch nach.
**Auf Standard zurücksetzen** löscht Ihre Fassung wieder.

## Platzhalter

rollen: admin

Platzhalter in geschweiften Klammern, etwa der Name des Empfängers, werden beim Versand
durch echte Werte ersetzt. Welche Platzhalter eine Vorlage kennt, steht neben dem Editor –
und genau danach richten sich auch die Felder der Vorschau.

Zwei Dinge, die regelmäßig überraschen:

- Ersetzt wird in **einem** Durchgang. Was als Wert eingesetzt wird, wird nicht noch einmal
  nach Platzhaltern durchsucht.
- Enthält ein Platzhalter HTML, braucht die Klartext-Fassung einen eigenen Wert – Tags haben
  im Klartext nichts verloren.

## Vorschau und Testmail

rollen: admin

Die Vorschau rendert live im echten Rahmen mit. Die **Testmail** geht an eine frei wählbare
Adresse, damit Sie das Ergebnis im richtigen Mailprogramm sehen.

Ein Link-Platzhalter bleibt in Vorschau und Testmail **immer** ein Beispiel-Link. Ein echter
Anmelde- oder Reset-Link an eine fremde Adresse wäre eine Kontoübernahme – deshalb wird er
dort gar nicht erst erzeugt.

## Der gemeinsame Rahmen

rollen: admin

Kopf und Fuß aller Mails stecken in einer eigenen Vorlage, dem Rahmen. Wer dort Logo oder
Farben ändert, ändert sie in allen Mails auf einmal.
