<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profilbilder: liegen auf der privaten Disk unter profilbilder/ und werden
 * beim Hochladen auf ein quadratisches JPEG (256 px) zugeschnitten, damit
 * Kopfzeile und Listen keine Handyfotos in voller Größe laden.
 */
class Profilbild
{
    public const ORDNER = 'profilbilder';

    private const KANTE = 256;

    public static function speichern(User $user, UploadedFile $datei): void
    {
        $pfad = self::ORDNER.'/'.$user->id.'-'.Str::random(8);
        $jpeg = self::zuschneiden($datei->getRealPath());

        if ($jpeg !== null) {
            $pfad .= '.jpg';
            Storage::disk('local')->put($pfad, $jpeg);
        } else {
            // Ohne GD (oder bei einem Format, das GD nicht lesen kann) das Original ablegen.
            $pfad .= '.'.($datei->guessExtension() ?: 'bin');
            Storage::disk('local')->put($pfad, file_get_contents($datei->getRealPath()));
        }

        self::loeschen($user);
        $user->forceFill(['profilbild' => $pfad])->save();
    }

    public static function entfernen(User $user): void
    {
        self::loeschen($user);
        $user->forceFill(['profilbild' => null])->save();
    }

    /** Adresse zum Einbinden; der Dateiname wechselt mit jedem Upload, das hält den Browser-Cache ehrlich. */
    public static function url(User $user): ?string
    {
        if (! $user->profilbild) {
            return null;
        }

        return route('profilbild', ['user' => $user->id, 'v' => pathinfo($user->profilbild, PATHINFO_FILENAME)], false);
    }

    private static function loeschen(User $user): void
    {
        if ($user->profilbild) {
            Storage::disk('local')->delete($user->profilbild);
        }
    }

    private static function zuschneiden(string $quelle): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $bild = @imagecreatefromstring((string) file_get_contents($quelle));
        if ($bild === false) {
            return null;
        }

        $bild = self::drehenNachExif($bild, $quelle);

        $b = imagesx($bild);
        $h = imagesy($bild);
        $seite = min($b, $h);

        $ziel = imagecreatetruecolor(self::KANTE, self::KANTE);
        // Transparenz (PNG/WebP) auf Weiß legen, JPEG kennt keinen Alphakanal.
        imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $bild, 0, 0, intdiv($b - $seite, 2), intdiv($h - $seite, 2), self::KANTE, self::KANTE, $seite, $seite);

        ob_start();
        imagejpeg($ziel, null, 85);

        return ob_get_clean() ?: null;
    }

    /** Handyfotos tragen die Drehung oft nur im EXIF – ohne Korrektur liegen sie quer. */
    private static function drehenNachExif(\GdImage $bild, string $quelle): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $bild;
        }

        $exif = @exif_read_data($quelle);
        $winkel = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $winkel === 0 ? $bild : (imagerotate($bild, $winkel, 0) ?: $bild);
    }
}
