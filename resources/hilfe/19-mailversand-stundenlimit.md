---
titel: Mailversand – Stundenlimit
route: admin.settings.mailversand
kategorie: Verwaltung
position: 19
---

rollen: admin

Wie viele Mails das Intranet höchstens pro Stunde verschickt. **0 oder leer bedeutet: kein
Limit.** Gezählt wird gleitend über die letzten 60 Minuten.

Das Limit drosselt den Ausgangskorb: Was darüber hinausgeht, wartet und geht in der nächsten
Stunde raus. Anmelde-Codes und Passwort-Links haben Vorfahrt. Der Wert steht bewusst hier und
nicht in einer Datei auf dem Server – so lässt er sich ändern, ohne dass jemand auf den Server
muss.

Was tatsächlich rausging, zeigt der Reiter **Maillog**.
