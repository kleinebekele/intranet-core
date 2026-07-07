<?php

namespace App\Support;

/**
 * Zeitbasierte Einmal-Codes (TOTP, RFC 6238) ohne externe Abhängigkeit.
 *
 * Kompatibel mit allen gängigen Authenticator-Apps (Vaultwarden/Bitwarden,
 * Google Authenticator, Aegis …): SHA-1, 6 Stellen, 30-Sekunden-Fenster.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 (RFC 4648)

    private const PERIOD = 30;

    private const DIGITS = 6;

    /** Neues Secret erzeugen (160 Bit, Base32-kodiert — Standardlänge). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** otpauth://-URI für QR-Codes bzw. den Import in die Authenticator-App. */
    public static function otpauthUri(string $issuer, string $account, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /**
     * Code prüfen. $window erlaubt ±N Zeitfenster Toleranz (Uhren-Drift
     * zwischen Handy und Server): window=1 akzeptiert den vorherigen,
     * aktuellen und nächsten 30-Sekunden-Code.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);

        foreach (range(-$window, $window) as $offset) {
            if (hash_equals(self::hotp(self::base32Decode($secret), $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** HMAC-basierter Einmal-Code (HOTP, RFC 4226) für einen Zählerstand. */
    private static function hotp(string $key, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);

        // "Dynamic Truncation": die letzten 4 Bit wählen die Startposition.
        $offset = ord($hash[19]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private static function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue; // Leer-/Fremdzeichen (z. B. aus Copy&Paste) ignorieren
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
