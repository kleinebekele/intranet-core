# Changelog

Alle nennenswerten Änderungen am **Core** der modularen Intranet-Plattform.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/);
Datumsangaben nach ISO (JJJJ-MM-TT). Module (z. B. `do1emu/module-news`,
`do1emu/module-userimport`) pflegen ihre Änderungen in ihren eigenen Repositories.

## [Unveröffentlicht]

### Hinzugefügt
- **Drei neue Modul-Icons:** `network` (bx-network-chart), `wifi` (bx-wifi) und `server`
  (bx-server). Erste Nutzung: das neue Netzwerk-Modul (`do1emu/module-netzwerk`) und seine
  künftigen Unterseiten (Karte, Switches, WLAN).
- **Der Ausgangskorb hat eine freie `referenz`-Spalte.** Ein Modul kann seine verschickten Mails
  darüber im Maillog gezielt wiederfinden – gesetzt über den internen Header `X-Intranet-Referenz`
  (`VorlagenMailer::senden(..., $quelle, $referenz)` bzw. `quelleMarkieren($nachricht, $quelle, $referenz)`),
  der beim Einliefern ausgelesen und wieder entfernt wird. Der Core wertet die Referenz nicht selbst
  aus. Erste Nutzung: das Newsletter-Modul zeigt damit auf der Ausgaben-Seite den echten
  Zustellstatus je Empfänger.
- **Eine über `Mail::html()` verschickte Mail kann ihren Auslöser fürs Maillog benennen.**
  `VorlagenMailer::senden()` nimmt dafür einen optionalen Namen; wer `Mail::html()` direkt
  aufruft, setzt ihn über `VorlagenMailer::quelleMarkieren($nachricht, 'Newsletter')`. Der Name
  reist als interner Header `X-Intranet-Quelle` zum Ausgangskorb, wird dort ausgelesen und wieder
  entfernt (er hat in der ausgehenden Mail nichts verloren). Bisher stand bei solchen Mails im
  Maillog nur „—", weil es keine Mailable-Klasse gibt, an der der Auslöser erkennbar wäre.
  Auslöser des Ganzen: das Newsletter-Modul.
- **Eine Vorlage kann sich ihren Rahmen aussuchen.** Bisher gab es genau einen (`_rahmen`), in den
  jede Mail gelegt wurde. `VorlagenDefinition` hat jetzt ein optionales Feld `rahmen`; `null`
  heißt „ich bin selbst einer". Damit dürfen Module eigene Rahmen anmelden – der Newsletter tut
  das, weil ein Rundbrief anders aussehen darf als eine Passwort-Mail. Bestehende Vorlagen merken
  nichts davon. Die Übersicht unter *Mailvorlagen* trennt jetzt **Rahmen** und **Mails**.
- **HTML- und Textfassung dürfen unterschiedliche Werte bekommen** (`VorlagenMailer::senden()`
  und `rendern()` nehmen ein zweites Werte-Array). Nötig, sobald ein Platzhalter HTML enthält:
  Im Klartext hätten Tags nichts verloren. Das Logo wurde seit jeher genauso behandelt – die
  Sonderbehandlung ist jetzt allgemein nutzbar.
- **Eine Vorlage kann eigene Vorschauwerte mitbringen** (`VorlagenDefinition::$beispiele`). Vorher
  zeigte der Editor für modul-eigene Platzhalter nur `[schluessel]`.
- Icon-Namen `envelope` und `send` für `<x-module-icon>`.

### Geändert
- **Der Mailvorlagen-Editor hat Reiter** statt zweier nebeneinanderliegender Spalten:
  *Formatierte Fassung* · *Reiner Text* · *Vorschau* (die Testmail sitzt jetzt in der Vorschau,
  wo sie hingehört). Betreff und Platzhalter-Knöpfe stehen darüber, weil sie in jedem Reiter
  gebraucht werden. Dadurch nimmt jedes Feld die volle Seitenbreite ein.
- **Der Rahmen (`_rahmen`) hat ein neues Standard-Layout**, nachgebaut nach dem Seitenlayout ohne
  Sidebar: weiße Kopfzeile mit grauer Unterkante statt des indigo Balkens, Haupttitel links,
  Logo rechts. Wer den Rahmen schon angepasst hat, behält seine Fassung – der neue Standard
  greift erst nach „Auf Standard zurücksetzen".
- **Der Rahmen wird nur noch als Quelltext bearbeitet.** Er ist ein vollständiges HTML-Dokument;
  ein Rich-Text-Feld verwirft `<!DOCTYPE>` und `<html>` schon beim Einlesen und schrieb den
  Rumpf beim Speichern zurück. Alle anderen Vorlagen sind HTML-Ausschnitte und behalten das
  Formatier-Feld.
- **Module ohne sichtbare Unterseite erscheinen nicht mehr in Sidebar und Dashboard.** Ein Modul,
  das (noch) keine Seiten mitbringt, hatte dort nur ein totes Ziel (`#`) – so ging es dem
  Linear-Modul auf der Waldorf-Instanz. Dieselbe Regel greift benutzerbezogen: Wer keinen
  einzigen Unterpunkt sehen darf, dem wird das Modul auch nicht mehr angeboten. In der
  **Modulverwaltung bleibt es unverändert sichtbar**, sonst ließe es sich weder einordnen
  noch entfernen.
- **`deploy.sh` zieht die eigenen Module automatisch nach.** Statt `composer install` läuft nun
  `composer update "do1emu/*"` – jeder Deploy holt die neueste per Constraint erlaubte Version der
  `do1emu/*`-Module (Fremdpakete bleiben lockgenau). Dazu startet `deploy.sh` sich selbst mit der
  neuen Fassung neu, wenn ein `git pull` das Skript verändert hat – so genügt **ein** `./deploy.sh`,
  auch wenn sich das Deploy-Skript selbst ändert. Setzt voraus, dass nur getaggt wird, was live soll.

### Behoben
- **Breite Inhalte ragten aus dem Formatier-Feld heraus.** Eine Mail-Tabelle mit fester Breite
  stand sichtbar über den Rand des Eingabekastens hinaus (gemessen: 1412 px Inhalt in einem
  880 px breiten Feld). Bilder und Tabellen werden jetzt auf die Feldbreite begrenzt, der Rest
  scrollt im Feld.
- **Die Live-Vorschau der Mailvorlagen hat nie aktualisiert.** Alpines Magie (`$root`, `$refs`)
  ist nur im Auswertungs-Kontext von Alpine verfügbar; in einem `setTimeout`- oder
  `await`-Callback ist sie `undefined`. Der Zugriff warf eine TypeError, die das `try/catch` der
  Vorschau als „unkritisch" verschluckte – die Vorschau blieb still auf dem ersten Stand stehen
  und sah dabei normal aus. Jetzt ist der Betreff regulärer Alpine-Zustand statt DOM-Zugriff, die
  Elementbezüge werden einmal in `init()` gemerkt, und eine laufende Nummer je Anfrage verhindert,
  dass eine ältere Antwort eine neuere überschreibt.
- **Der Rahmen rahmte sich in seiner eigenen Vorschau selbst ein** (zwei `<body>`). Beim
  Bearbeiten des Rahmens wird er jetzt direkt gerendert; sein `{{ inhalt }}` kommt aus den
  Vorschau-Werten.

### Hinzugefügt
- **Vorschau-Werte je Vorlage.** Der Editor zeigt genau die Platzhalter *dieser* Vorlage als
  Eingabefelder (statt eines festen Benutzer-Dropdowns) – bei einer Ekkon-Meldung also
  `ueberschrift`/`text`/`quelle`, beim Passwort-Reset `name`/`link`. Kennt eine Vorlage `name`,
  gibt es zusätzlich eine Suche nach Name/E-Mail, die einen echten Benutzer übernimmt.
- **Spalte `users.import_email`** + **Mailvorlage „Anmelde-Adresse geändert" (`login_geaendert`).**
  Gegenstück zu `import_name` für die E-Mail; die neue Vorlage ist im Backend bearbeitbar wie die
  anderen und geht an die **alte** Adresse, wenn ein Import die Anmelde-E-Mail eines bereits
  registrierten Benutzers ändert. (Genutzt vom Linear-Benutzerabgleich.)
- **Spalte `users.import_name`.** Merkt sich den Namen, den ein Import zuletzt gesetzt hat.
  Damit können importierende Module (z. B. der Linear-Benutzerabgleich) unterscheiden, ob ein
  Benutzer seinen Namen selbst geändert hat: Stimmt `name` noch mit `import_name` überein, zieht
  eine Namensänderung aus der Quelle nach; weichen sie ab, bleibt die selbst gewählte Fassung.
- **Mailvorlagen bearbeiten (Verwaltung → Mailvorlagen).** Betreff, HTML und Textfassung jeder
  Mail lassen sich anpassen – mit formatierter Eingabe (WYSIWYG), Umschalter auf den
  HTML-Quelltext und Live-Vorschau im echten Rahmen. Ein gemeinsames Layout (`_rahmen`)
  umschließt jede Mail. Beide Fassungen (HTML + Text) werden verschickt. Platzhalter
  (`{{ name }}` usw.) werden per Textersetzung eingesetzt, **nicht** über Blade gerendert –
  wer eine Vorlage bearbeitet, kann damit keinen Code ausführen. Gespeichert wird nur, was vom
  Standard abweicht; „Auf Standard zurücksetzen" löscht die Anpassung, sodass eine spätere
  Core-Verbesserung wieder greift. Einladung und Passwort-Reset nutzen die Vorlagen bereits;
  Module können eigene anmelden. Dazu eine **Testmail** an eine frei eingegebene Adresse mit den
  aktuell im Editor stehenden Texten, wahlweise mit den Daten eines gewählten Benutzers – der
  `link`-Platzhalter bleibt dabei aus Sicherheitsgründen immer ein Beispiel.
- **Einladungs-Puffer (Verwaltung → Einladungen).** Importe merken Zugangslinks nur vor;
  verschickt wird erst nach Freigabe durch einen Menschen — einzeln oder alle auf einmal.
  Ein Import legt schnell hunderte Benutzer an, und verschickte Mails holt man nicht zurück.
  Der Reiter zeigt die Zahl der Wartenden, die Administratoren bekommen **eine** Sammelmeldung
  (nicht eine Mail je Benutzer). Benutzer mit künstlicher Adresse werden gar nicht erst
  vorgemerkt, und mehrfaches Vormerken erzeugt keine Dubletten — ein zweiter Importlauf ist
  damit ungefährlich.
- **Benutzer sperren statt löschen.** Neue Felder `gesperrt_am` / `gesperrt_grund`, Schalter in
  der Benutzer-Verwaltung. Wer die Schule verlässt, soll sich nicht mehr anmelden können –
  seine Bestellungen, Zeugnisse und Protokolleinträge müssen aber bleiben; Löschen wäre dafür
  das falsche Werkzeug. Die Sperre wirkt **sofort**, auch in einer laufenden Sitzung
  (Middleware `GesperrteAbmelden`) – beim nächtlichen Abgleich ist die offene Sitzung von
  gestern der Regelfall, nicht die Ausnahme. Das eigene Konto lässt sich nicht sperren.
- **Künstliche Mailadressen können den Server nicht verlassen.** Adressen mit Endungen aus
  `mail.unzustellbare_endungen` (Standard `.intern`) werden weder eingeliefert noch versendet.
  Nötig für importierte Schüler ohne eigene Mailadresse. Gemischte Empfänger werden bereinigt
  statt verworfen, damit eine Rundmail nicht an einem einzigen Schüler scheitert.
- **`php artisan modules:uninstall <key>` – das Gegenstück zu `modules:sync`.** Bisher gab es
  nur den Weg hinein: `composer remove` ließ Modul-Zeile, Menüpunkte samt Rollen, sprechende
  Adressen und die Tabellen des Moduls zurück. Der neue Befehl zeigt erst, was am Modul hängt
  (inklusive Tabellen und deren Zeilenzahlen), fragt nach und räumt dann auf.
  Standardmäßig **nur die Registrierung** – Tabellen fasst er erst mit `--mit-daten` an,
  `--dry-run` zeigt alles nur an.
  ⚠️ Reihenfolge: erst deinstallieren, **dann** `composer remove` – mit dem Paket verschwinden
  seine Migrationsdateien, danach ist kein Zurückrollen mehr möglich. Der Befehl erkennt diesen
  Fall und verweigert `--mit-daten`, statt stillschweigend nur die halbe Arbeit zu machen.
  Damit der Core weiß, welche Migration zu welchem Modul gehört, trägt das `ModuleManifest`
  jetzt sein Paketverzeichnis (`basePath`) – gesetzt vom `ModuleServiceProvider`, Module
  müssen nichts tun.
- **Deinstallieren räumt auch dann auf, wenn das Paket schon weg ist.** Neue Tabelle
  `module_migrations`: Bei jedem `modules:sync` merkt sich der Core, welche Migration zu
  welchem Modul gehört und welche Tabellen sie anlegt. Diese Aufzeichnung überlebt das Paket.
  Vorher galt: kein Paket → keine Migrationsdatei → kein `down()` → die Tabelle blieb für
  immer stehen, und der Haken *„Auch die Tabellen löschen"* wurde gar nicht erst angeboten.
  Jetzt wird sie in dem Fall direkt verworfen; der Dialog sagt auch dazu, dass es diesmal ohne
  `down()` geschieht. Bleibende Grenze: Migrationen, die nur bestehende Tabellen ändern, sind
  ohne ihr `down()` nicht rückgängig zu machen – und wer vor dieser Änderung verwaist ist, hat
  keine Aufzeichnung.
- **Entfernen-Knopf unter Verwaltung → Module.** Derselbe Vorgang ohne Konsole: eingeklappt
  hinter *„Modul entfernen"*, zeigt vorher, was daran hängt – inklusive der Tabellen des
  Moduls mit ihrer aktuellen Zeilenzahl. Das Häkchen *„Auch die Tabellen des Moduls löschen"*
  verlangt zusätzlich, den Modul-Schlüssel abzutippen. Die Erfolgsmeldung nennt den
  `composer remove`-Befehl, denn das Paket muss weiterhin von Hand aus der `composer.json` –
  sonst steht das Modul beim nächsten `modules:sync` wieder da.

### Geändert
- **SEO-Liste nach Bereichen gebündelt.** Die Übersicht führt den Bereich an, alles darunter
  hängt eingeklappt daran – aufzuklappen über *„X Unterlinks"*. In einem gewachsenen System
  standen sonst hunderte gleichrangiger Zeilen untereinander, und die eine, die man ändern
  will, ging darin unter. Bewusst nur **eine** Ebene: Auch `…ekkon.notifications.create` hängt
  direkt unter `…ekkon.index`, statt Klapp-Ebenen in Klapp-Ebenen zu schachteln.
- **Geerbter Pfad ist sichtbar.** Bei einer Unterseite steht der Pfad, der sich aus dem
  Stammpfad ergibt, als graue Vorgabe im Feld (`rollen/create`) – man sieht also, was
  passiert, wenn man nichts tut, statt es sich denken zu müssen.
- **Technische Namen ohne `.index`.** Aus `module.ekkon.index` wird `module.ekkon`. Nur die
  Anzeige; gespeichert wird weiterhin der vollständige Routen-Name.
- **SEO-Liste lesbar gemacht:** In der Spalte *Seite* stand fast überall „Index" – das ist nur
  die technische Bezeichnung für die Übersicht einer Gruppe und sagte niemandem, welche Seite
  gemeint ist. Jetzt steht dort die **Beschriftung aus der Seitenleiste** („Kategorien"), also
  das Wort, unter dem man die Seite kennt und das man im Admin selbst pflegt. Seiten ohne
  Menüpunkt bekommen einen Notbehelf aus dem Routen-Namen, ebenfalls ohne das angehängte `.index`.
- **Eine Adresse gilt für den ganzen Bereich.** Bisher wurde nur die eine Seite umbenannt, deren
  Zeile man bearbeitet hat – die Übersicht lag danach unter `/kategorien`, das Bearbeiten aber
  weiter unter `/modules/schulkantine/kategorien/1/bearbeiten`. Jetzt wirkt der Eintrag als
  **Stammpfad**: Alles, was darunter liegt, zieht mit. Ein eigener Eintrag für eine Unterseite
  schlägt den Stammpfad. Verglichen wird bewusst mit Schrägstrich, damit `kategorien-import`
  nicht mitwandert, nur weil es gleich anfängt.

### Hinzugefügt
- **Adressen von Unterseiten sind relativ zum Bereich.** Im Feld steht nur der eigene Teil
  (`benachrichtigungen`), der Bereich davor als fester Vorsatz (`ekkon/`). Zusammen ergibt
  das `/ekkon/benachrichtigungen`. Wird der Bereich später auf `ekkon3` geändert, wandert
  alles darunter **automatisch** mit – ohne dass man eine einzige Unterseite anfasst.
- **Häkchen „Absoluter Pfad"** je Unterseite: Der Eintrag gilt dann als vollständige Adresse
  statt relativ zum Bereich, die Seite steht also bewusst außerhalb. Neue Spalte
  `route_settings.absoluter_pfad`.
- Die Eindeutigkeit auf `route_settings.pfad` entfällt: Bei relativen Angaben dürfen zwei
  Bereiche beide ein `benachrichtigungen` haben – daraus werden ja zwei verschiedene
  Adressen. Kollisionen prüft der Controller jetzt an der **fertigen** Adresse.

### Behoben
- **Routen mit derselben Adresse wandern gemeinsam.** `GET /admin/roles` zeigt die Übersicht,
  `POST /admin/roles` legt an – nur die erste trägt einen Namen und damit den Eintrag. Die
  namenlose überschrieb die bereits umbenannte, je nach Reihenfolge des Routers: Es stand
  „Gespeichert", die Adresse blieb aber stehen. Nebenbei zeigt das Formular der Übersicht
  nach dem Umbenennen jetzt nicht mehr ins Leere.
- **Sprechende Adressen wirkten überhaupt nicht.** Menüpunkte zeigten weiter auf die
  technische Adresse, der direkte Aufruf der neuen endete im 404. Ursache: Die Umschreibung
  lief im `boot()` des `AppServiceProvider` – Laravel lädt die Routen aber erst in einem
  `booted`-Callback, also **nach** allen Providern, bei aktivem `route:cache` sogar in einem
  zweiten, noch später laufenden. Umgeschrieben wurde damit an einer Sammlung, die es zu dem
  Zeitpunkt noch gar nicht gab. Jetzt erledigt das eine globale Middleware, die im Kernel vor
  dem Routing läuft – dort sind die Routen garantiert da, mit und ohne Cache.
- **`generated::…`-Einträge in der SEO-Liste.** `route:cache` vergibt unbenannten Routen selbst
  einen Namen; die Liste hielt sie deshalb für Seiten. Trat nur auf Servern mit Routen-Cache auf,
  lokal nie.
- **Adresse ließ sich nicht zurücknehmen.** Beim Leeren von Adresse und Titel wurde der Eintrag
  über die Abfrage gelöscht – das löst keine Model-Ereignisse aus, der zwischengespeicherte
  Eintrag blieb also bestehen und die alte Wunschadresse galt weiter.

### Hinzugefügt
- **`deploy.sh`:** Ein Aufruf statt einer Handvoll getippter Befehle – Wartungsmodus,
  `git pull`, Composer, Assets, Migrationen, Caches, und am Ende kommt die Seite auch dann
  wieder hoch, wenn unterwegs etwas schiefgeht. Die Pfade zu PHP/Composer/npm unterscheiden
  sich je Server (Plesk 8.5 per vollem Pfad, VM anders), deshalb stehen sie in einer
  nicht versionierten `deploy.env` daneben; Vorlage: `deploy.env.example`.
- **SEO (Verwaltung → Reiter SEO):** Jede Seite kann eine **sprechende Adresse** und einen
  **festen Titel** bekommen. Die Adresse **ersetzt** die bisherige – auch Menüpunkte und
  interne Verweise zeigen danach dorthin, ohne dass ein Modul etwas ändern muss. Möglich wird
  das, indem die Route **selbst** umgeschrieben wird (`RoutenAliase`, im `boot()`) statt
  Laravels URL-Erzeugung zu unterwandern: Der Ersatz greift dadurch an beiden Enden zugleich,
  beim Auflösen einer Anfrage und bei jedem `route()`-Aufruf. Verträgt sich mit `route:cache`,
  weil die bereits geladene Sammlung im Speicher umgebaut wird.
  Die alte Adresse bleibt als **Weiterleitung** bestehen (302, nicht 301 – ein Alias lässt sich
  jederzeit zurücknehmen, eine dauerhafte Weiterleitung brennt sich in Browsern fest).
  Angeboten werden nur benannte GET-Seiten **ohne Platzhalter**; Kollisionen mit bestehenden
  Adressen werden abgefangen, sonst könnte man die Startseite verdecken. Die Liste ist nach
  Modul und Volltext filterbar (ohne Knopf). Neue Tabelle `route_settings`.
  Ohne vergebenen Alias entsteht **kein Aufwand**: Ist die Tabelle leer, passiert im `boot()`
  gar nichts.
- **Einstellungen (Verwaltung → erster Reiter):** Werte, die Administratoren im Betrieb ändern
  können sollen, ohne an die `.env` zu müssen – **Haupttitel**, **Logo** und **Favicon**
  (beide als Upload, Logo erscheint in Kopfzeile und auf der Anmeldeseite) sowie das
  **Mail-Stundenlimit**. Neue Tabelle `settings` als Schlüssel/Wert-Speicher (gecacht), damit
  nicht jede neue Kleinigkeit eine Migration erzwingt. Das in der Verwaltung gesetzte
  Stundenlimit wird **ausschließlich hier** gepflegt – es hängt am Vertrag des Mailproviders,
  nicht am Server, und steht darum bewusst nicht in der `.env`. Standard: kein Limit.
- **Browser-Titel nach Konvention `{Haupttitel} – {Modul} – {Seite}`.** Module müssen dafür
  **nichts tun**: Das Modul ergibt sich aus dem Routen-Namen, die Seite aus dem passenden
  Menüpunkt (längster Treffer gewinnt, damit Unterseiten den Oberpunkt erben). Wo das nicht
  reicht, setzt die View den Teil selbst: `<x-slot name="titel">Schüler bearbeiten</x-slot>`.
  Leere Teile und Wiederholungen fallen weg – das Dashboard heißt schlicht `{Haupttitel}`.
  Das Favicon wird wurzelrelativ eingebunden (nicht über `APP_URL`), weil dieses Intranet oft
  über mehrere Adressen erreichbar ist – intern per IP, extern per Domain.
- **Mail-Ausgangskorb (`mail_outbox`):** Alle ausgehenden E-Mails werden zwischengelagert
  und vom neuen Task `mail:ausliefern` im erlaubten Takt verschickt. Löst zwei Dinge auf
  einmal: die **Drosselung** (Stundenlimit aus der Verwaltung, gleitend über 60 Minuten – der
  Provider der Waldorfschule erlaubt nur 250 Mails je Stunde) und ein **Versand-Protokoll**
  (Zeitpunkt, Empfänger, Betreff, Status, Fehlertext, Message-ID), das Laravel von sich aus
  nicht führt. Die Message-ID ist der Schlüssel, um später Zustellmeldungen des Providers
  (zugestellt/unzustellbar) daran zu hängen.
  Abgefangen wird über `MessageSending` – gibt der Listener `false` zurück, bricht Laravel
  den Sofortversand ab. **Module müssen dafür nichts tun und nichts wissen**; auch künftige
  laufen automatisch mit. Zeitkritische Mails (2FA-Code, Passwort-Link) haben Vorfahrt in
  der Warteschlange, konfigurierbar über `mail.outbox.eilig`.
  ⚠️ **Braucht einen Cron für `schedule:run`** – ohne den bleibt der Korb voll und es geht
  keine Mail mehr raus. Zum Abschalten: `MAIL_OUTBOX=false` (dann verhält sich alles wie
  vorher, ungedrosselt und ohne Protokoll).
- **Menü-Gruppen:** Module mit vielen Unterseiten können verwandte Punkte optisch unter
  einer aufklappbaren Überschrift bündeln – im Manifest über den neuen Parameter
  `group:` an `->item(...)`, z. B. `->item('schuljahre', 'Schuljahre', …, group: 'Verwaltung')`.
  Neue Spalte `group_label` an `module_menu_items` (nullable, ohne Gruppe wie bisher eine
  eigene Zeile). Die Gruppe erscheint an der Position ihres ersten Mitglieds und ist
  aufgeklappt, wenn der aktive Punkt darin liegt.
  Bewusst **rein optisch**: keine Eltern-Einträge, keine Verschachtelung in der Datenbank.
  Jeder Menüpunkt bleibt ein eigener Eintrag mit eigenen Rollen – an `EnsureModuleAccess`,
  `Module::homeUrl()` und der Modul-Verwaltung (Drag & Drop) ändert sich nichts. Eine Gruppe,
  von der ein Benutzer keinen einzigen Punkt sehen darf, erscheint gar nicht erst.
- **Cookie-Hinweis:** schlichtes Banner (unten), das darüber informiert, dass die Seite
  ausschließlich technisch notwendige Cookies verwendet – ohne Ablehnen-Option. Erscheint
  auf eingeloggten Seiten und der Login-Seite; die Bestätigung wird lokal im Browser
  gemerkt (`localStorage`, kein zusätzliches Cookie). Partial `layouts/cookie-notice`.
- **Zeitzone konfigurierbar:** `APP_TIMEZONE` in der `.env` (Default weiterhin `UTC`,
  Empfehlung `Europe/Berlin`). Wirkt auf Anzeige, `now()` und den Task-Scheduler.

### Behoben
- **„Dieses Gerät merken" (2FA) überlebt jetzt Deploys:** Der serverseitige Trust-Token
  lag im App-Cache und wurde damit von `cache:clear`/`optimize:clear` (Teil jedes Deploys)
  mitgelöscht — gemerkte Geräte wurden faktisch bei jedem Deploy vergessen. Der Token liegt
  jetzt in einer eigenen Tabelle `two_factor_trusted_devices` und übersteht Cache-Leerungen.
  Sicherheitsmodell (verschlüsseltes Cookie **+** serverseitiger Abgleich) unverändert.
- **Tailwind scannt Modul-PHP:** Klassen, die Module aus PHP-Code liefern (nicht nur
  aus Blade-Views), landen jetzt im CSS-Build (`vendor/do1emu/module-*/src/**/*.php`).

### Geändert (Sicherheit)
- **Modul-Sichtbarkeit = Zugriffsrecht (Default-Deny):** Die Rollen an den Menü-Unterpunkten
  (Verwaltung → Module) steuern jetzt Navigation UND Seitenzugriff (neue Middleware
  `EnsureModuleAccess` an der `web`-Gruppe, greift für alle `module.{key}.*`-Routen).
  Neue Regel: **keine Rolle am Punkt = nur Administratoren**; „für alle" wählt man explizit
  über die Basis-Rolle `user`. Technische Routen ohne eigenen Menüpunkt sind erreichbar,
  wenn der Benutzer mindestens einen Punkt des Moduls sehen darf. Ein Modul erscheint im
  Menü, sobald ein Unterpunkt sichtbar ist (Modul-Rollen entfallen; `admins_only` bleibt
  als harte Sperre). **Bestands-Migration:** vorhandene rollenlose Punkte erhalten einmalig
  die Rolle `user`, damit sich bestehende Installationen nicht ändern.
- **Selbst-Registrierung standardmäßig geschlossen:** `/register` leitet zum Login um,
  sobald mindestens ein Benutzer existiert. `REGISTRATION_ENABLED=true` in der `.env`
  öffnet sie wieder. Ausnahme: auf frischen Installationen (0 Benutzer) bleibt sie offen,
  damit der erste Admin angelegt werden kann.

### Hinzugefügt
- **Zwei-Faktor-Authentifizierung** — immer verfügbar, **Opt-in je Benutzer** im Profil:
  Standard-Faktor ist ein 6-stelliger Code per E-Mail (10 Min gültig, max. 5 Versuche,
  Neuversand-Drossel); optional **TOTP** per Authenticator-App (z. B. Vaultwarden;
  QR-Code + Secret, Bestätigung per erstem Code) statt Mail-Codes. TOTP nach RFC 6238
  ohne externe Abhängigkeit (`App\Support\Totp`). Die Sperre hängt global an der
  `web`-Gruppe und schützt automatisch auch alle Modul-Routen.
  `.env`: **`FORCE_2FA=true`** macht 2FA für alle Benutzer verpflichtend;
  **`TWO_FACTOR_REMEMBER_DAYS`** (Standard 30, 0 = aus) steuert „Dieses Gerät merken"
  bei der Code-Abfrage (verschlüsseltes Cookie + serverseitige Gegenprüfung).
- **Admin: „TOTP zurücksetzen"** in der Benutzer-Verwaltung (z. B. bei Handy-Verlust) —
  der Benutzer fällt auf Mail-Codes zurück und kann TOTP neu einrichten.
- Feature-Tests für die komplette 2FA-Strecke (`tests/Feature/TwoFactorTest.php`).
- Weitere Icons in der `module-icon`-Whitelist (`restaurant`, `edit`, `back`, `plus`,
  `trash`, `download`, `search`, `save`, `x`, `like`, `trophy`) – für die Oberflächen der Module.

### Geändert
- Tailwind-`content` scannt jetzt auch die Blade-Views installierter Module
  (`vendor/do1emu/module-*/resources/views`), damit deren CSS-Klassen im Build landen.

## [0.2.0] - 2026-07-02

### Hinzugefügt
- **Rollen-System:** Tabellen `roles` (mit sprechendem Schlüssel `role_id`) und
  `user_roles` (n:n), Beziehung `User::roles()`.
- **Rollen-Adminpanel** (*Verwaltung → Rollen*): Rollen anlegen, umbenennen und löschen.
- **System-Rollen `admin` und `user`** (`is_system`): fest und unlöschbar. Jeder
  Benutzer erhält automatisch die Rolle `user` (auch beim Import); Bestand per
  Migration nachgetragen.
- **Aktion „Alle Zuweisungen aufheben"** für Nicht-System-Rollen (mit Warn-Modal);
  Löschen nur noch bei Rollen ohne Zuweisungen möglich.
- **Benutzer-Verwaltung** (*Verwaltung → Benutzer*): CRUD, Rollen zuweisen/entziehen,
  E-Mail unveränderbar, Willkommens-Mail für neue Benutzer, Passwort-Reset-Link.
- **Filter in der Benutzerliste:** Suche über Name/E-Mail und Filter nach Rolle.
- **Rollenbasierte Navigation:** pro Modul und pro Unterpunkt festlegen, welche
  Rollen es in der Navigation sehen. Zusätzlicher Schalter „Nur für Admins";
  Admins sehen immer alles; keine Auswahl bedeutet „für alle sichtbar".
- **Boxicons-Icon-Bibliothek** (selbst gehostet über npm/Vite), durchgängig in
  Sidebar, Header, Dashboard, Admin-Panels und Formularen.

### Geändert
- `users`-Tabelle erweitert: `externe_id`, `source` und optionales `password`
  (für Import und Einladungs-Flow).
- Komponente `module-icon` auf Boxicons umgestellt; Größe/Farbe über Utility-Klassen.
- Import-Modul `do1emu/module-userimport` auf **v1.0.1** aktualisiert (über Packagist).

## [0.1.0] - 2026-07-01

### Hinzugefügt
- **Initiales Grundgerüst** der modularen Intranet-Plattform (Laravel + Breeze):
  Modul-Vertrag mit Auto-Discovery, linke Navigation, Admin-Panel für Modul-Reihenfolge
  und An-/Abschalten, Admin-Kennzeichnung (`is_admin`, erster Benutzer wird Admin).

### Geändert
- Core-Paketname auf `do1emu/intranet-core` umgestellt.
- News-Modul wird über **Packagist** bezogen (`do1emu/module-news` `^1.0`).
- Entwickler-/KI-Wissen nach `CLAUDE.md` ausgelagert, öffentliche `README.md` neutralisiert;
  Modul-Vorlage und Lock auf PHP `^8.3` angepasst.

[Unveröffentlicht]: https://github.com/kleinebekele/intranet-core/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/kleinebekele/intranet-core/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/kleinebekele/intranet-core/releases/tag/v0.1.0
