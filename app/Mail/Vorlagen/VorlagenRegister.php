<?php

namespace App\Mail\Vorlagen;

/**
 * Das Verzeichnis aller Mailvorlagen: ihre Standardtexte und die Platzhalter,
 * die sie kennen.
 *
 * Module können hier später eigene Vorlagen anmelden (per {@see registrieren()}),
 * so wie Tasks ihre Einstellungen deklarieren. Der Core bringt die Vorlagen mit,
 * die zu ihm gehören.
 */
class VorlagenRegister
{
    /** @var array<string, VorlagenDefinition> */
    private array $definitionen = [];

    public function __construct()
    {
        $this->coreVorlagen();
    }

    public function registrieren(VorlagenDefinition $definition): void
    {
        $this->definitionen[$definition->schluessel] = $definition;
    }

    /** @return array<string, VorlagenDefinition> */
    public function alle(): array
    {
        return $this->definitionen;
    }

    public function finden(string $schluessel): ?VorlagenDefinition
    {
        return $this->definitionen[$schluessel] ?? null;
    }

    /**
     * Die versendbaren Vorlagen (alle außer dem Rahmen), für die Übersicht.
     *
     * @return array<string, VorlagenDefinition>
     */
    public function versendbare(): array
    {
        return array_filter(
            $this->definitionen,
            fn (VorlagenDefinition $d) => $d->schluessel !== VorlagenDefinition::RAHMEN,
        );
    }

    private function coreVorlagen(): void
    {
        // Der Rahmen umschließt jede versendbare Mail. {{ inhalt }} ist die
        // Stelle, an der die einzelne Mail eingesetzt wird.
        $this->registrieren(new VorlagenDefinition(
            schluessel: VorlagenDefinition::RAHMEN,
            titel: 'Rahmen (Layout aller Mails)',
            beschreibung: 'Kopf und Fuß, die jede Mail umschließen. {{ inhalt }} ist der Platz für den eigentlichen Text.',
            platzhalter: [
                'inhalt' => 'Der Inhalt der jeweiligen Mail (nicht selbst eintippen)',
                'titel' => 'Haupttitel aus den Einstellungen',
                'logo' => 'Logo aus den Einstellungen (leer, wenn keins hinterlegt ist)',
                'jahr' => 'Aktuelles Jahr',
            ],
            betreff: null,
            html: self::RAHMEN_HTML,
            text: self::RAHMEN_TEXT,
        ));

        $this->registrieren(new VorlagenDefinition(
            schluessel: 'einladung',
            titel: 'Einladung (Zugang anlegen)',
            beschreibung: 'Bekommt ein neuer Benutzer, damit er sein Passwort festlegt.',
            platzhalter: [
                'name' => 'Name des Empfängers',
                'link' => 'Button-Adresse zum Passwort-Festlegen',
                'titel' => 'Haupttitel aus den Einstellungen',
            ],
            betreff: 'Willkommen – bitte lege dein Passwort fest',
            html: self::EINLADUNG_HTML,
            text: self::EINLADUNG_TEXT,
        ));

        $this->registrieren(new VorlagenDefinition(
            schluessel: 'passwort_reset',
            titel: 'Passwort zurücksetzen',
            beschreibung: 'Der Link von „Passwort vergessen" und aus der Benutzer-Verwaltung.',
            platzhalter: [
                'name' => 'Name des Empfängers',
                'link' => 'Button-Adresse zum Zurücksetzen',
            ],
            betreff: 'Passwort zurücksetzen',
            html: self::RESET_HTML,
            text: self::RESET_TEXT,
        ));

        $this->registrieren(new VorlagenDefinition(
            schluessel: 'zwei_faktor',
            titel: 'Anmelde-Code (2FA)',
            beschreibung: 'Der einmalige Code für die Zwei-Faktor-Anmeldung.',
            platzhalter: [
                'name' => 'Name des Empfängers',
                'code' => 'Der sechsstellige Code',
                'minuten' => 'Gültigkeitsdauer in Minuten',
            ],
            betreff: 'Dein Anmelde-Code',
            html: self::ZWEIFAKTOR_HTML,
            text: self::ZWEIFAKTOR_TEXT,
        ));

        $this->registrieren(new VorlagenDefinition(
            schluessel: 'login_geaendert',
            titel: 'Anmelde-Adresse geändert',
            beschreibung: 'Geht an die ALTE Adresse, wenn sich die Anmelde-E-Mail eines bereits '
                .'registrierten Benutzers ändert (z. B. weil sie im Linear-Abgleich korrigiert wurde).',
            platzhalter: [
                'name' => 'Name des Empfängers',
                'neue_mail' => 'Die neue Anmelde-Adresse',
            ],
            betreff: 'Deine Anmelde-Adresse hat sich geändert',
            html: self::LOGIN_GEAENDERT_HTML,
            text: self::LOGIN_GEAENDERT_TEXT,
        ));
    }

    // ── Standardtexte ────────────────────────────────────────────────────────
    // Bewusst schlichtes, tabellenbasiertes Mail-HTML mit Inline-Styles: Nur so
    // sieht es in Outlook, Gmail & Co. verlässlich gleich aus.

    // Nachgebaut ist das Seitenlayout ohne die Sidebar: weiße Kopfzeile mit
    // grauer Unterkante (wie `layouts/header.blade.php`), darin der Haupttitel
    // links und das Logo rechts. Die Kopfzeile trägt deshalb KEINE Farbfläche
    // mehr – ein Logo mit weißem Hintergrund stand auf dem früheren indigo
    // Balken als sichtbarer Kasten.
    private const RAHMEN_HTML = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr><td style="background:#ffffff;padding:16px 32px;border-bottom:1px solid #e5e7eb;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="left" valign="middle" style="color:#1f2937;font-size:18px;font-weight:bold;">{{ titel }}</td>
              <td align="right" valign="middle">{{ logo }}</td>
            </tr>
          </table>
        </td></tr>
        <tr><td style="padding:32px;">
          {{ inhalt }}
        </td></tr>
        <tr><td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
          <span style="color:#9ca3af;font-size:12px;">© {{ jahr }} {{ titel }}</span>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    private const RAHMEN_TEXT = <<<'TEXT'
{{ titel }}

{{ inhalt }}

—
© {{ jahr }} {{ titel }}
TEXT;

    private const EINLADUNG_HTML = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;">Hallo {{ name }}!</p>
<p style="margin:0 0 16px;">Für dich wurde ein Zugang zum Intranet angelegt. Bitte lege über den folgenden Button dein persönliches Passwort fest:</p>
<p style="margin:0 0 24px;">
  <a href="{{ link }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;">Passwort jetzt festlegen</a>
</p>
<p style="margin:0;color:#6b7280;font-size:14px;">Der Link ist aus Sicherheitsgründen nur begrenzt gültig. Ist er abgelaufen, nutze auf der Anmeldeseite „Passwort vergessen".</p>
HTML;

    private const EINLADUNG_TEXT = <<<'TEXT'
Hallo {{ name }}!

Für dich wurde ein Zugang zum Intranet angelegt. Bitte lege über den folgenden
Link dein persönliches Passwort fest:

{{ link }}

Der Link ist aus Sicherheitsgründen nur begrenzt gültig. Ist er abgelaufen,
nutze auf der Anmeldeseite „Passwort vergessen".
TEXT;

    private const RESET_HTML = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;">Hallo {{ name }}!</p>
<p style="margin:0 0 16px;">Du hast angefragt, dein Passwort zurückzusetzen. Über den folgenden Button legst du ein neues fest:</p>
<p style="margin:0 0 24px;">
  <a href="{{ link }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;">Neues Passwort festlegen</a>
</p>
<p style="margin:0;color:#6b7280;font-size:14px;">Wenn du das nicht warst, kannst du diese Mail ignorieren – dein Passwort bleibt unverändert.</p>
HTML;

    private const RESET_TEXT = <<<'TEXT'
Hallo {{ name }}!

Du hast angefragt, dein Passwort zurückzusetzen. Über den folgenden Link legst
du ein neues fest:

{{ link }}

Wenn du das nicht warst, kannst du diese Mail ignorieren – dein Passwort bleibt
unverändert.
TEXT;

    private const ZWEIFAKTOR_HTML = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;">Hallo {{ name }}!</p>
<p style="margin:0 0 16px;">Dein Anmelde-Code lautet:</p>
<p style="margin:0 0 24px;font-size:32px;font-weight:bold;letter-spacing:6px;color:#4f46e5;">{{ code }}</p>
<p style="margin:0;color:#6b7280;font-size:14px;">Der Code ist {{ minuten }} Minuten gültig. Gib ihn niemandem weiter.</p>
HTML;

    private const ZWEIFAKTOR_TEXT = <<<'TEXT'
Hallo {{ name }}!

Dein Anmelde-Code lautet: {{ code }}

Der Code ist {{ minuten }} Minuten gültig. Gib ihn niemandem weiter.
TEXT;

    private const LOGIN_GEAENDERT_HTML = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;">Hallo {{ name }}!</p>
<p style="margin:0 0 16px;">Deine Anmelde-Adresse für das Intranet wurde geändert. Ab sofort meldest du dich <strong>nur noch</strong> mit dieser Adresse an:</p>
<p style="margin:0 0 24px;font-size:18px;font-weight:bold;color:#4f46e5;">{{ neue_mail }}</p>
<p style="margin:0 0 16px;">Dein Passwort bleibt unverändert – nur die Adresse, mit der du dich anmeldest, ist neu.</p>
<p style="margin:0;color:#6b7280;font-size:14px;">Diese Nachricht geht an deine bisherige Adresse. Hast du die Änderung nicht veranlasst, wende dich bitte an die Verwaltung.</p>
HTML;

    private const LOGIN_GEAENDERT_TEXT = <<<'TEXT'
Hallo {{ name }}!

Deine Anmelde-Adresse für das Intranet wurde geändert. Ab sofort meldest du
dich NUR NOCH mit dieser Adresse an:

{{ neue_mail }}

Dein Passwort bleibt unverändert – nur die Adresse, mit der du dich anmeldest,
ist neu.

Diese Nachricht geht an deine bisherige Adresse. Hast du die Änderung nicht
veranlasst, wende dich bitte an die Verwaltung.
TEXT;
}
