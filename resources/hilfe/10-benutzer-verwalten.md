---
titel: Benutzer verwalten
route: admin.users.index
kategorie: Verwaltung
position: 10
---

rollen: admin

Unter **Verwaltung → Benutzer** liegen alle Konten. Hier werden Rollen vergeben, Zugänge
zurückgesetzt und Konten gesperrt.

## Sperren statt löschen

rollen: admin

Für ausgeschiedene Personen ist das Schloss-Symbol der richtige Weg, nicht der Papierkorb:

- Die Sperre wirkt **sofort**, auch in einer bereits geöffneten Sitzung. Wer gerade
  angemeldet ist, fliegt beim nächsten Klick heraus.
- Alles, was der Person zugeordnet ist, bleibt erhalten und nachvollziehbar.
- Ein Löschen wäre endgültig und würde Zuordnungen mitreißen.

Das eigene Konto lässt sich nicht sperren – sonst könnte man sich selbst aussperren.

## Passwort und zweiter Faktor zurücksetzen

rollen: admin

Zwei getrennte Knöpfe, zwei verschiedene Zwecke:

- **Reset-Link schicken** sendet dem Benutzer eine Mail mit einem Link zum Neusetzen des
  Passworts. Sie sehen das Passwort nie.
- **App-Verknüpfung lösen** entfernt die hinterlegte Authenticator-App. Das ist der Fall
  "Handy verloren".

## Übernommene Konten

rollen: admin

Stammt ein Konto aus einem Import, merkt sich das Intranet Name und Adresse, die der Import
zuletzt gesetzt hat. Solange der Benutzer sie nicht selbst geändert hat, zieht eine Korrektur
in der Quelle automatisch nach. Weicht der Wert ab, wurde er selbst gewählt und bleibt stehen.

Ändert ein Import die **Anmelde-Adresse** eines bereits registrierten Benutzers, geht eine
Benachrichtigung an die **alte** Adresse – sonst erführe die Person nie, womit sie sich ab
jetzt anmeldet.

## Wer wird Administrator

rollen: admin

Der allererste Benutzer einer Installation wird automatisch Administrator. Weitere legt man
auf der Kommandozeile an:

```
php artisan intranet:admin person@beispiel.de
```

Das Administrator-Kennzeichen ist bewusst kein Rollen-Häkchen im Backend – es hebelt alle
Sichtbarkeitsregeln aus und soll deshalb eine bewusste Handlung auf dem Server sein.
