# Intranet-Wächter (Windows)

Ein PowerShell-Skript auf einem **anderen Rechner im selben Netz** (z. B. Datenbank-Server, Domänencontroller)
fragt alle 5 Minuten das Lebenszeichen des Intranets ab und schickt bei einer Störung **direkt per SMTP** eine Mail –
am Intranet vorbei, das ja gerade gestört sein kann.

Störung heißt: keine oder eine fehlerhafte Antwort (Server, Webserver, PHP oder Datenbank weg) oder `"ok": false`
(der Scheduler hat seit über 10 Minuten keinen Task gestartet). Gemeldet wird nach 2 Fehlversuchen in Folge, dann
stündlich eine Erinnerung und einmal die Entwarnung.

Gegenrichtung: Das Intranet merkt sich jede Abfrage. Bleibt sie länger als 20 Minuten aus (Wächter-Rechner aus,
Aufgabe kaputt), meldet der Task `Waechter/Lebenszeichen` das über die Meldungsart `waechter-still`.

## Einrichten

1. Im Intranet unter **Ekkon → Webhook-Eingang** eine Quelle anlegen (z. B. „Wächter"). Die Abfrage-Adresse ist die
   Webhook-Adresse der Quelle plus `/lebenszeichen`:
   `https://<intranet>/webhooks/ekkon/<schluessel>/lebenszeichen`
2. Den Ordner `tools/waechter` auf den Wächter-Rechner kopieren, z. B. nach `C:\Intranet-Waechter`.
3. `waechter.beispiel.json` nach `waechter.json` kopieren und ausfüllen (`name`, `url`, `smtp`). `passwort_geschuetzt`
   leer lassen; ohne `benutzer` wird ohne Anmeldung verschickt (interner Relay).
4. In einer **PowerShell als Administrator**:
   ```powershell
   powershell -ExecutionPolicy Bypass -File C:\Intranet-Waechter\einrichten.ps1 -Konfig C:\Intranet-Waechter\waechter.json
   ```
   Fragt das SMTP-Kennwort (wird an diesen Rechner gebunden verschlüsselt abgelegt), schickt eine Testmail und legt
   die geplante Aufgabe „Intranet-Waechter" an (alle 5 Minuten, als SYSTEM).

Protokoll: `waechter.log`, Zustand: `waechter-zustand.json` – beide neben der Konfiguration.

## Hinweise

- SMTP mit STARTTLS (Port 587, `"ssl": true`) oder unverschlüsselt (Port 25, interner Relay). Port 465 (TLS von
  Anfang an) kann `Send-MailMessage` nicht.
- `absender_name` (optional) ist der Anzeigename des Absenders, z. B. „Intranet-Wächter".
- Scheitert eine Alarm-Mail, versucht der nächste Lauf sie erneut.
- Testmail von Hand: `intranet-waechter.ps1 -Konfig …\waechter.json -Testmail`.
