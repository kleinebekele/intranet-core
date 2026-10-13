<?php

namespace App\Support;

/**
 * IP-Adressen von Personen nur gekürzt speichern (Datenschutz): Die ersten
 * beiden Blöcke reichen, um Netz bzw. Anbieter zu erkennen – die einzelne
 * Person steckt in den hinteren.
 *
 *  - IPv4 `203.0.113.57`  → `203.0.0.0`
 *  - IPv6 `2001:db8:85a3::1` → `2001:db8::`
 */
class IpKuerzung
{
    public static function kuerzen(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $teile = explode('.', $ip);

            return $teile[0].'.'.$teile[1].'.0.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = bin2hex(inet_pton($ip));

            return inet_ntop(hex2bin(substr($hex, 0, 8).str_repeat('0', 24)));
        }

        // Schon gekürzt oder kein gültiger Wert: lieber nichts speichern.
        return null;
    }
}
