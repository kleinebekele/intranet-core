# CLAUDE.md — Ekkon

Interne Hinweise für die Arbeit an Ekkon (Entwickler + KI-Assistenten).
Die Kurzfassung für Modul-Autoren steht in [EKKON.md](../../EKKON.md).

Ekkon ist **kein Fachmodul, sondern die Laufzeit für wiederkehrende Aufgaben**. Fachmodule
melden ihre Tasks bei der Registry an und nutzen Protokoll, Sperren und Benachrichtigungen.
Seit 2026-09 fester Bestandteil des Cores: Code hier in `app/Ekkon/`, Views in
`resources/views/ekkon/` (Namensraum `ekkon::`), Routen in `routes/ekkon.php`, Konfiguration
in `config/ekkon.php`, Migrationen zwischen denen des Cores. Pfadangaben `src/…` weiter unten
meinen `app/Ekkon/…`.

⚠️ **Altnamen:** Fachmodule erben zum Teil noch von `Intranet\Modules\Ekkon\…` (altes Paket
`do1emu/module-ekkon`). `Altnamen.php` bildet das per `class_alias` ab; der Provider bindet die
TaskRegistry unter BEIDEN Namen auf dieselbe Instanz. Wer hier eine Klasse umbenennt oder
verschiebt, bricht diese Module. Liegt das alte Paket noch MIT Code in `vendor/`, hält sich der
Core-Provider komplett zurück (`Altnamen::altesPaketAktiv()`).

⚠️ **Migrationen** tragen dieselben Dateinamen wie im alten Paket – auf bestehenden Instanzen
gelten sie dadurch als gelaufen. Nicht umbenennen.

```bash
php artisan ekkon:task <Gruppe/Name>   # einen Task von Hand starten
```

## Der Task-Vertrag

Ein Task erbt von `EkkonTask` (`src/Tasks/EkkonTask.php`) und liefert:

- `schedule()` — Cron-Ausdruck
- `run(): array` — die eigentliche Arbeit

Darin:

| Aufruf | Wirkung |
|---|---|
| `$this->msg(...)` | Klartext ins Protokoll |
| `$this->debug[...]` | strukturierte Daten zum Lauf |
| `$this->benachrichtige(...)` | Meldung an die Routing-Tabelle |

⚠️ Die Registry hält Tasks als **Singleton** — `resetChannels()` leert die Kanäle vor jedem Lauf.
Ohne das schleppt ein Task Meldungen des Vorlaufs mit.

`TaskRegistry::addSource($dir, $ns, $paket)` meldet die Tasks eines Fachmoduls an
(`src/Support/TaskRegistry.php`). Die Registry wird mit **`singletonIf`** gebunden — dieselbe
Provider-Reihenfolge-Falle wie im Core: Paket-Provider laufen **vor** dem `AppServiceProvider`,
ein hartes `singleton` würde die bereits gefüllte Registry mit einer leeren überschreiben.

## Läufe und Sperren

`TaskRunner` (`src/Support/TaskRunner.php`) schreibt jeden Lauf nach `ekkon_task_runs`
(Status, Dauer, `messages`, `debug`) und sperrt gegen Überlappung.

⚠️ **`lockSeconds()` muss länger sein als der längste Lauf** (Default 600 s). Sonst verfällt die
Sperre mitten im Lauf, ein zweiter Lauf startet parallel und beide schreiben dieselben
Schlüssel — der Fehler sieht dann wie ein Datenproblem aus, nicht wie ein Sperrproblem.

**Aufbewahrung:** `debug` nur für die letzten **10 Läufe** je Task (`TaskRunner::pruneDebug`),
die Lauf-Historie **14 Tage** (Scheduler-Eintrag im `EkkonServiceProvider`).
Ein auffälliger Lauf eines Minuten-Tasks ist also schnell weg — vorher rauskopieren.

## Benachrichtigungen

Das Routing passiert beim **Anlegen** (`src/Services/Benachrichtiger.php`), der Versand ist
bewusst dumm (`src/Tasks/Notifications/SendNotifications.php`).

- Kein `if ($meldungsart === …)` im Versand — Fallunterscheidung gehört in die Routing-Tabelle.
- Meldungsarten deklariert der Task selbst (`public array $meldungsarten`); die Maske bietet
  nur diese an.
- Teams läuft über einen **Workflow**, nicht über den klassischen Connector (der ist abgekündigt).
- Zustellung über eigene Tabelle + Task, bewusst **nicht** über die Laravel-Queue.
- Liegengebliebene Meldungen (`failed`, `ohne_ziel`) meldet der Provider an die **Glocke** des
  Cores (`App\Support\Hinweise`, `hinweiseAnmelden()`), nur für Admins. Ohne die Klasse
  (älterer Core) passiert nichts.

## Webhook-Eingang

`POST /webhooks/ekkon/{schluessel}` (Route `ekkon.webhook.empfangen`, bewusst OHNE `web`-Middleware:
keine Session, kein CSRF; Drossel 600/min – Carrier-Push-Dienste schicken je Ereignis eine Nachricht,
in Wellen). Der Schlüssel in der URL ist das Passwort, unbekannt/inaktiv
→ 404. Alles wird roh gespeichert (`ekkon_webhook_eingaenge`: Header ohne Authorization/Cookie, Body
bis 1 MB, IP gekürzt); Admin-Seite „Webhook-Eingang" zeigt und löscht. Fachlogik (z. B. Sally.io-
Zusammenfassungen nach Titel routen) kommt als Task obendrauf, nicht in den Empfang.

Erster Task darauf (liegt im **RAV-Fachmodul `module-ekkon-jtl`**, nicht hier — die Basis bleibt
firmenneutral): `Webhooks/SallyZusammenfassung` (alle 5 Min): erkennt Sally-Eingänge am Inhalt
(`recordingSummaryId` + `appointmentSubject`), macht je Termin-Titel eine Meldungsart `sally-<slug>`
(WG/AW-Präfixe entfernt; Liste entsteht aus den bisher gesehenen Titeln → erster Eingang landet auf
`ohne_ziel`, danach Route anlegen). Benachrichtigungen tragen seitdem optional `html`: Mail = HTML +
Klartext (`VorlagenMailer` mit `$textWerte`), Teams = Markdown (`SupportHtmlText`).

## Anhang an einer Benachrichtigung (Datei nach Teams/Mail)

`benachrichtige(…, anhang: ['name' => 'x.docx', 'inhalt' => $bytes])` legt die Datei einmal unter
`storage/app/ekkon/anhaenge/<JJJJ-MM>/` ab; alle Zielzeilen zeigen darauf (`anhang_pfad`/`anhang_name`).
Mail hängt sie an (`VorlagenMailer::senden(…, anhaenge: …)`, der Ausgangskorb speichert sie mit).
Teams: Eine Adaptive Card kann keine Datei tragen – `TeamsWebhookClient` schickt sie **Base64 im Feld
`datei`** neben `type`/`attachments` mit. Der Workflow muss sie selbst ablegen, sonst ignoriert er das
Feld stillschweigend. Prune nach 14 Tagen löscht die Datei, sobald keine Zeile mehr darauf zeigt.

**Workflow in Power Automate erweitern** (Teams-Kanal → ⋯ → Workflows → den vorhandenen „Post to a
channel when a webhook request is received" bearbeiten):
1. Trigger „When a Teams webhook request is received" behalten. Beim JSON-Schema nichts ändern
   (Zusatzfelder kommen über `triggerBody()` trotzdem durch).
2. Vor „Post card in a chat or channel" einen Schritt **Bedingung**: `empty(triggerBody()?['datei'])`
   ist gleich `false`.
3. Im Ja-Zweig **SharePoint → „Datei erstellen"**: Websiteadresse = SharePoint-Site des Teams,
   Ordnerpfad = `/Freigegebene Dokumente/<Kanalname>` (Kanalordner), Dateiname =
   `triggerBody()?['datei']?['name']`, Dateiinhalt = `base64ToBinary(triggerBody()?['datei']?['inhalt'])`.
4. Danach der bisherige Post-Schritt; optional im Kartentext den Link aus „Datei erstellen"
   (`outputs('Datei_erstellen')?['body/{Link}']`) ergänzen. Die Datei ist im Reiter **Dateien** des
   Kanals sichtbar.
5. Grüner HTTP-Status beweist nichts (s. `TeamsWebhookClient`): Nach dem Umbau eine echte Meldung
   auslösen und im Kanal nachsehen (Benachrichtigungen → „nochmal senden").

Soll im Kanal **nur der Link** statt der ganzen Karte stehen (Sally-Daily): den Block „Attachments is
null" samt Post-Card entfernen; im Ja-Zweig nach „Datei erstellen" **Teams → „Nachricht in einem Chat
oder Kanal posten"** mit `triggerBody()?['titel']` + Link; im Nein-Zweig dieselbe Aktion mit
`triggerBody()?['text']`. Der Umschlag trägt `titel` und `text` dafür flach neben `attachments`.
„Datei erstellen" liefert **keinen** Link, nur `body/Path` – der Link wird gebaut, `?web=1` öffnet
die Datei in Word Online statt sie herunterzuladen (so läuft es seit 22.09.2026 im Daily-Kanal):
`concat('<a href="SITE/', replace(outputs('Datei_erstellen')?['body/Path'], ' ', '%20'), '?web=1">Zusammenfassung als Word-Dokument</a>')`

Erster Nutzer: `SallyZusammenfassung` (RAV, `module-ekkon-jtl`) baut die Zusammenfassung per PhpWord
als `.docx` (`Support/SallyWordDokument`).

## Teams über Graph (Chat-ID statt Webhook) – echte Dateikarte

Power Automate kann in **Besprechungschats** nicht posten (Office-365-Groups-Connector: nur `groups`;
generische HTTP-Aktion = Premium) und eine Datei nie als Dateikarte anhängen. Deshalb zweiter Weg:
`ekkon_teams_channels.chat_id` gesetzt → `TeamsGraphClient` postet direkt über Microsoft Graph im
Namen des verbundenen Kontos (`ekkon_graph_konten`, Refresh-Token verschlüsselt, `GraphKontoVerbindung`
erneuert den Access-Token stündlich). Anhang: Upload in den SharePoint-Ordner `ablage_url` (Drive über
die webUrl der Site-Drives erkannt, Bibliotheksname ist sprachabhängig) und als `reference`-Anhang mit
der GUID aus dem eTag an die Nachricht – so sieht es aus wie manuell geteilt, öffnet in Teams.
Mit Datei bleibt die Nachricht kurz (Titel + Fakten + Karte); ohne Datei Text/HTML wie bisher.

Ziel-ID: Chat `19:…@thread.v2` (Link auf eine Nachricht kopieren), Team-Kanal `<Team-GUID>/19:…@thread.tacv2`,
**Person** = ihre E-Mail-Adresse: dann legt Graph den 1:1-Chat zwischen verbundenem Konto und Person an
(`POST /chats`, oneOnOne, ID einen Tag gecacht) und gibt ihr die hochgeladene Datei per `/invite`
(write, ohne Mail) frei – auf den Ordner selbst hat sie ja keinen Zugriff. Das verbundene Konto darf
ein neutraler M365-Benutzer sein; er braucht eine Teams-Lizenz, Mitgliedschaft in den Ziel-Chats und
Zugriff auf die Ablage-Ordner.
Einmalig in der Entra-App der Anmeldung: Umleitungs-URI `…/modules/ekkon/benachrichtigungen/microsoft/callback`
und delegierte Berechtigungen `offline_access`, `Chat.ReadWrite`, `ChannelMessage.Send`, `Sites.ReadWrite.All`,
`Team.ReadBasic.All`, `Channel.ReadBasic.All` (letztere für „Zugriffe anzeigen": `GraphAuskunft` listet Chats,
Teams/Kanäle und Sites samt IDs/Bibliotheks-URLs zum Kopieren)
(Admin-Zustimmung). Danach Benachrichtigungen → Teams-Channels → „Microsoft-Konto verbinden".
„Test senden" postet auf diesem Weg eine Nachricht mit kleiner Textdatei. Refresh-Token ungültig
(Passwortwechsel, Entzug) → `letzter_fehler` in der Maske, neu verbinden.

## Sicherheitsschalter

Ohne **`EKKON_TASKS_ENABLED=true`** läuft **kein** Task — auch nicht „jetzt ausführen" in der
Oberfläche und auch nicht `artisan ekkon:task`. In Entwicklungsumgebungen bewusst nicht setzen.

Der Laravel-Scheduler braucht außerdem genau **einen** Cron-Eintrag
(`* * * * * php artisan schedule:run`) — ohne ihn läuft kein Task.

## MSSQL: immer über PDO_ODBC

Nie nativ, sondern über `MSSQL_ODBC_DSN` — so haben alle Umgebungen dieselben Treiber-Macken.
Bekannte Fallen:

- SQL-`NULL` kommt als **Leerstring** an, Zahlen als String. `=== null` läuft ins Leere, ein
  `UPDATE` trifft dann lautlos 0 Zeilen → Rohzeile an der Grenze normalisieren und die Wirkung
  per `COUNT` prüfen.
- Rohe `datetime`-Spalten in einem größeren SELECT können den Puffer zerlegen (die
  *Nachbar*-Spalte kommt mit Binärmüll zurück) → `CONVERT(varchar(19), …, 120)` nutzen.
- `[` ist im `LIKE`-Muster ein Sonderzeichen: `LIKE '[%'` matcht lautlos **nichts**.
  JSON-Array-Prüfung stattdessen über `LEFT(LTRIM(x),1) = '['`.
- Ein nicht indexfähiger Vergleich (z. B. `REPLACE()` um die Spalte) kippt einen Seek in einen
  Vollscan; bei leerer Treffermenge fehlt zusätzlich der frühe Abbruch. Dann in zwei Schritte
  zerlegen statt eine große Abfrage zu bauen.
