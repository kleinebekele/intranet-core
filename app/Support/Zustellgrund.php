<?php

namespace App\Support;

/**
 * Kurzform eines Zustellgrunds aus der Antwort des empfangenden Mailservers, z. B.
 * „550 5.1.1 … No such mailbox" → „Adresse gibt es nicht". Die volle Antwort bleibt
 * gespeichert und erscheint beim Darüberfahren (Maillog).
 *
 * Zuerst zählt der erweiterte Statuscode (RFC 3463, „5.1.1"), dann typische
 * Stichwörter – viele Server schicken nur „550" mit Freitext. Was nicht erkannt
 * wird, bekommt eine allgemeine Kurzform nach 4xx/5xx.
 */
class Zustellgrund
{
    /**
     * Stichwörter vor Codes: Sie sind eindeutiger als ein nacktes „550". Reihenfolge zählt.
     *
     * @var array<string, string>
     */
    private const MUSTER = [
        '/\(DIAL\)|dialup|dynamic ip|transient ip/i' => 'Unser Server als Privatanschluss abgelehnt',
        '/block ?list|blacklist|blocked using|spamhaus|abusix|\brbl\b|listed at|client host \[[^\]]*\] blocked/i' => 'Unser Server steht auf einer Sperrliste',
        '/\[TSS0\d\]|temporarily deferred|rate limit|too many (messages|connections|mails)|unexpected volume|try again later/i' => 'Empfänger bremst (zu viele Mails)',
        '/smtputf8/i' => 'Sonderzeichen in der Adresse',
        '/message (size )?exceeds|too large|message size/i' => 'Mail zu groß',
        '/quota|mailbox (is )?full|over ?quota|insufficient (system )?storage|exceeded storage/i' => 'Postfach voll',
        '/account (is )?(disabled|inactive|suspended)|mailbox (is )?disabled|deactivated/i' => 'Postfach gesperrt',
        '/no such (user|mailbox|recipient)|user unknown|unknown user|does not exist|mailbox unavailable|recipient (address )?rejected|invalid recipient|unknown recipient|address rejected|not found in directory|mailbox not found/i' => 'Adresse gibt es nicht',
        '/host or domain name not found|name service error|domain not found|no mx|nxdomain/i' => 'Domain gibt es nicht',
        '/connection timed out|timed out|connection refused|network is unreachable|no route to host|lost connection/i' => 'Server des Empfängers nicht erreichbar',
        '/spam|policy|not accepted|content rejected/i' => 'Vom Empfänger als unerwünscht abgelehnt',
    ];

    /** @var array<string, string> erweiterte Statuscodes (x.y.z, nur y.z zählt) */
    private const CODES = [
        '1.1' => 'Adresse gibt es nicht',
        '1.2' => 'Domain gibt es nicht',
        '1.10' => 'Adresse gibt es nicht',
        '2.1' => 'Postfach gesperrt',
        '2.2' => 'Postfach voll',
        '3.4' => 'Mail zu groß',
        '4.4' => 'Server des Empfängers nicht erreichbar',
        '4.7' => 'Zustellung dauert zu lange',
    ];

    private const AUFGEGEBEN = 'Nach mehreren Tagen aufgegeben – zuletzt: ';

    public static function kurz(?string $grund): string
    {
        $grund = trim((string) $grund);
        if ($grund === '') {
            return '';
        }

        if (str_starts_with($grund, self::AUFGEGEBEN)) {
            $rest = self::kurz(mb_substr($grund, mb_strlen(self::AUFGEGEBEN)));

            return 'Nach 5 Tagen aufgegeben'.($rest !== '' ? ' – '.$rest : '');
        }

        foreach (self::MUSTER as $muster => $text) {
            if (preg_match($muster, $grund)) {
                return $text;
            }
        }

        // Nicht Teil einer IP-Adresse (1.2.3.4): weder Ziffer/Punkt davor noch „.Ziffer" danach.
        if (preg_match('/(?<![\d.])([245])\.(\d{1,3})\.(\d{1,3})(?![\d.])/', $grund, $c)) {
            $text = self::CODES[$c[2].'.'.$c[3]] ?? null;
            if ($text !== null) {
                return $text;
            }
            if ($c[2] === '7') {
                return 'Vom Empfänger als unerwünscht abgelehnt';
            }
        }

        if (preg_match('/(?<![\d.])([45])\d\d(?![\d.])/', $grund, $c)) {
            return $c[1] === '4' ? 'Vorübergehender Fehler beim Empfänger' : 'Vom Empfänger abgelehnt';
        }

        return mb_strimwidth($grund, 0, 80, '…');
    }
}
