<?php

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;

/**
 * Versionsangabe für die Fußzeile.
 *
 * Plattform: aus `git describe` – letzter Tag `vX.Y.Z` plus die Zahl der Commits
 * danach als Patch-Stufe (v1.0.0 + 12 Commits = v1.0.12). Jeder Deploy mit neuem
 * Core-Code hebt sie also an. deploy.sh schreibt den Wert nach dem Pull in
 * storage/app/version.txt; ohne die Datei (lokal) fragt die Klasse git selbst.
 *
 * Module: die installierten do1emu/*-Pakete laut Composer.
 */
final class Version
{
    public const DATEI = 'app/version.txt';

    public static function plattform(): string
    {
        $datei = storage_path(self::DATEI);
        if (is_file($datei) && ($roh = trim((string) @file_get_contents($datei))) !== '') {
            return self::formatieren($roh);
        }

        return Cache::remember('intranet.version', 300, function () {
            $roh = @exec('git -C '.escapeshellarg(base_path()).' describe --tags --long --match "v[0-9]*" 2>&1', $aus, $code);

            return $code === 0 ? self::formatieren((string) $roh) : 'v1.0';
        });
    }

    /** „v1.0.0-12-gabc1234" → „v1.0.12"; alles andere unverändert. */
    public static function formatieren(string $describe): string
    {
        if (preg_match('/^v(\d+)\.(\d+)\.(\d+)-(\d+)-g[0-9a-f]+$/', trim($describe), $m)) {
            return 'v'.$m[1].'.'.$m[2].'.'.((int) $m[3] + (int) $m[4]);
        }

        return trim($describe) !== '' ? trim($describe) : 'v1.0';
    }

    /**
     * Version je Modul-Schlüssel (Manifest-Key): Das Paket ist das, in dessen
     * Installationsordner die Provider-Klasse des Moduls liegt. Module im Core
     * selbst (Ekkon) haben kein eigenes Paket und fehlen hier.
     *
     * @return array<string, string>
     */
    public static function jeModul(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        $pfade = [];
        $selbst = InstalledVersions::getRootPackage()['name'] ?? '';
        foreach (InstalledVersions::getInstalledPackages() as $paket) {
            if ($paket === $selbst || ! ($pfad = InstalledVersions::getInstallPath($paket))) {
                continue;
            }
            if ($echt = realpath($pfad)) {
                $pfade[$paket] = rtrim(str_replace('\\', '/', $echt), '/').'/';
            }
        }

        $ergebnis = [];
        foreach (app()->getProviders(\App\Modules\Support\ModuleServiceProvider::class) as $provider) {
            $datei = realpath((string) (new \ReflectionClass($provider))->getFileName());
            if ($datei === false) {
                continue;
            }
            $datei = str_replace('\\', '/', $datei);
            foreach ($pfade as $paket => $ordner) {
                if (str_starts_with($datei, $ordner)) {
                    $v = (string) InstalledVersions::getPrettyVersion($paket);
                    $ergebnis[$provider->manifest()->key] = preg_match('/^\d/', $v) ? 'v'.$v : $v;
                    break;
                }
            }
        }

        return $ergebnis;
    }

    /** @return array<string, string>  Kurzname (ohne do1emu/) → Version */
    public static function module(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        $module = [];
        $selbst = InstalledVersions::getRootPackage()['name'] ?? '';
        foreach (InstalledVersions::getInstalledPackages() as $paket) {
            if (! str_starts_with($paket, 'do1emu/') || $paket === $selbst) {
                continue;
            }
            $v = (string) InstalledVersions::getPrettyVersion($paket);
            $module[substr($paket, 7)] = preg_match('/^\d/', $v) ? 'v'.$v : $v;
        }
        ksort($module);

        return $module;
    }
}
