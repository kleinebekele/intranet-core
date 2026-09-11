<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Ein SMTP-Absender: eigenes Postfach mit eigenem Server-Zugang.
 *
 * Verwaltet unter Verwaltung → Maillog → SMTP-Absender. Module bieten die
 * Konten zur Auswahl an (z. B. der Newsletter je Ausgabe) und markieren die
 * Mail mit {@see anMail()}; der Ausgangskorb schickt sie dann über diesen
 * Zugang statt über den Standard-Mailer der Instanz.
 *
 * Der Laravel-Mailer eines Kontos heißt `konto-<id>` und wird erst bei Bedarf
 * aus der Datenbank in die Mail-Konfiguration eingehängt ({@see registrieren()}) –
 * die `config/mail.php` bleibt unberührt.
 */
class MailKonto extends Model
{
    protected $table = 'mail_konten';

    /** Header, an dem der Ausgangskorb erkennt, über welches Konto die Mail rausgeht. */
    public const MAILER_HEADER = 'X-Intranet-Mailer';

    public const PRAEFIX = 'konto-';

    public const VERSCHLUESSELUNGEN = [
        'tls' => 'STARTTLS (meist Port 587)',
        'ssl' => 'SSL/TLS (meist Port 465)',
        'keine' => 'keine (nur intern)',
    ];

    protected $fillable = [
        'bezeichnung', 'absender_mail', 'absender_name', 'antwort_an',
        'host', 'port', 'verschluesselung', 'benutzername', 'passwort', 'aktiv',
    ];

    protected function casts(): array
    {
        return [
            'passwort' => 'encrypted',
            'aktiv' => 'boolean',
            'port' => 'integer',
        ];
    }

    /** Name des Laravel-Mailers dieses Kontos. */
    public function mailerName(): string
    {
        return self::PRAEFIX.$this->id;
    }

    /** Das Konto zu einem Mailer-Namen (`konto-<id>`) – oder null. */
    public static function ausMailerName(?string $name): ?self
    {
        if ($name === null || ! str_starts_with($name, self::PRAEFIX)) {
            return null;
        }

        $id = (int) substr($name, strlen(self::PRAEFIX));

        return $id > 0 ? static::find($id) : null;
    }

    /**
     * Das Konto als Laravel-Mailer verfügbar machen. Danach funktioniert
     * `Mail::mailer($konto->mailerName())`. Mehrfachaufruf ist harmlos.
     */
    public function registrieren(): string
    {
        $name = $this->mailerName();

        config(['mail.mailers.'.$name => [
            'transport' => 'smtp',
            'scheme' => match ($this->verschluesselung) {
                'ssl' => 'smtps',
                'keine' => 'smtp',
                default => null, // STARTTLS: Laravel/Symfony handeln es auf 587 selbst aus
            },
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->benutzername ?: null,
            'password' => $this->passwort ?: null,
            'timeout' => 15,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ]]);

        // Ein früher gebauter Mailer gleichen Namens (z. B. nach Bearbeiten des
        // Kontos im selben Prozess) darf nicht mit alten Zugangsdaten weiterleben.
        Mail::purge($name);

        return $name;
    }

    /**
     * Eine Nachricht auf dieses Konto ausrichten: Absenderadresse des Kontos,
     * Anzeigename und Antwort-an (Vorgaben des Kontos, vom Aufrufer überschreibbar)
     * und die Markierung, über die der Ausgangskorb den Zugang wählt.
     */
    public function anMail(Message $nachricht, ?string $absenderName = null, ?string $antwortAn = null): void
    {
        $nachricht->from($this->absender_mail, filled($absenderName) ? $absenderName : (string) ($this->absender_name ?? ''));

        $antwort = filled($antwortAn) ? $antwortAn : $this->antwort_an;
        if (filled($antwort)) {
            $nachricht->replyTo($antwort);
        }

        $nachricht->getSymfonyMessage()->getHeaders()->addTextHeader(self::MAILER_HEADER, $this->mailerName());
    }
}
