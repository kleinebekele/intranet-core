---
titel: Anmeldung mit dem Microsoft-Konto
route: admin.microsoft.index
kategorie: Verwaltung
position: 18
---

rollen: admin

Wer im Haus schon mit einem Microsoft-365-Konto arbeitet, kann sich damit auch im Intranet
anmelden – ohne zweites Passwort. Diese Seite zeigt, ob das eingerichtet ist, welche Werte
gelten, und wer es in letzter Zeit versucht hat.

## Wer hereinkommt

rollen: admin

Zwei Fälle, und der Unterschied ist wichtig:

**Bestehende Benutzer** melden sich immer per Microsoft an. Beim ersten Mal wird das
Microsoft-Konto über die **E-Mail-Adresse** dem vorhandenen Intranet-Konto zugeordnet, danach
zählt eine unveränderliche Kennung von Microsoft – ein späterer Namenswechsel schadet also
nicht.

**Unbekannte** bekommen nur dann automatisch ein Konto, wenn sie Mitglied einer freigegebenen
Microsoft-365-Gruppe sind. Welche Gruppen das sind, steht in der `.env` des Servers unter
`MS_GRUPPEN`. Ist dort nichts eingetragen, wird **niemand** automatisch angelegt.

Ein gesperrtes Intranet-Konto bleibt gesperrt – auch über Microsoft kommt da niemand herein.

## Rollen

rollen: admin

Was in `MS_ROLLEN` steht, bekommt **jeder**, der sich über Microsoft anmeldet – auch Konten, die
es schon lange gibt. Rollen werden dabei nur **ergänzt**, nie entzogen: Was ein Admin von Hand
vergeben hat, bleibt stehen.

## Das Passwort entfällt – außer bei Administratoren

rollen: admin

Sobald ein Konto einmal über Microsoft hereingekommen ist, gilt es als Microsoft-Konto: Die
Anmeldung mit E-Mail und Passwort wird ab dann **abgelehnt**, mit dem Hinweis auf den
Microsoft-Knopf. Auch „Passwort vergessen" läuft für diese Konten nicht mehr – es würde ja
nichts nützen. Im eigenen Profil steht der Hinweis dazu.

**Administratoren sind ausgenommen.** Ihr Passwort bleibt gültig, damit bei einer Störung bei
Microsoft niemand vor der eigenen Tür steht. In der Benutzerliste steht bei ihnen deshalb
„Passwort bleibt gültig".

Und noch eine Sicherung: Wird die Microsoft-Anmeldung in der `.env` wieder abgeschaltet, ist
der Passwort-Weg für **alle** sofort wieder offen. Niemand bleibt ausgesperrt.

## Einen Benutzer von Hand umstellen

rollen: admin

In der Benutzerübersicht gibt es je Zeile einen Knopf, der den Anmeldeweg umschaltet:

- **Microsoft-Symbol** – ab jetzt nur noch über Microsoft. Das geht auch bei jemandem, der sich
  hier noch nie so angemeldet hat (praktisch für neue Kollegen). Achtung: Wenn zu der Adresse
  kein Microsoft-Konto gehört, kommt derjenige damit gar nicht mehr herein.
- **Schlüssel-Symbol** – Passwort wieder erlauben. Das ist der Rückweg, falls jemand versehentlich
  ausgesperrt wurde.

Unter dem Namen steht jeweils, was gerade gilt und ob es von Hand festgelegt wurde. Bei
Administratoren erscheint der Knopf nicht – ihr Passwort gilt immer.

## Was mit der Zwei-Faktor-Abfrage passiert

rollen: admin

Wer sich über Microsoft anmeldet, wird im Intranet **nicht** noch einmal nach einem Code
gefragt. Der zweite Faktor ist bei Microsoft schon abgehandelt worden; eine zweite Abfrage
wäre eine Hürde ohne zusätzlichen Schutz. Für die Anmeldung mit Passwort ändert sich nichts.

## Der Passwort-Weg bleibt

rollen: admin

Die gewohnte Anmeldung mit E-Mail und Passwort bleibt daneben bestehen. Das ist Absicht:
Sollte Microsoft einmal nicht erreichbar sein, kommt man trotzdem noch in sein eigenes
Intranet.

## Wenn es nicht klappt

rollen: admin

In der Tabelle **Letzte Anmeldeversuche** steht bei jedem Versuch, woran es lag. Die
häufigsten Fälle:

- *Kein Intranet-Konto* – die Adresse ist hier unbekannt und es ist keine Gruppe freigegeben.
  Konto in der Benutzerverwaltung anlegen, dann klappt der nächste Versuch.
- *Nicht in der freigegebenen Gruppe* – das Microsoft-Konto ist in keiner der Gruppen aus
  `MS_GRUPPEN`.
- *Fehler* mit Hinweis auf die Tokenkonfiguration – in der App-Registrierung fehlt der
  optionale Anspruch `groups`. Ohne ihn kann das Intranet die Gruppen nicht sehen.
