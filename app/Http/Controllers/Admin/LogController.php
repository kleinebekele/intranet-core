<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Zeigt die letzten Eintraege der Laravel-Logdateien im Browser (Admin-Bereich),
 * damit man fuer die Fehlersuche nicht mehr per SSH ins Logfile schauen muss.
 *
 * Bewusst nur lesend und auf die letzten Bytes je Datei beschraenkt – Logs
 * koennen sehr gross werden.
 */
class LogController
{
    /** Wie viele Bytes vom Ende jeder Datei gelesen werden (letzte 300 KB). */
    private const LESE_BYTES = 300 * 1024;

    /** Hoechstzahl angezeigter Eintraege. */
    private const MAX_EINTRAEGE = 300;

    public function index(Request $request): View
    {
        $verzeichnis = storage_path('logs');
        $dateien = collect(glob($verzeichnis.'/*.log') ?: [])
            ->sortByDesc(fn (string $p) => filemtime($p))
            ->map(fn (string $p) => basename($p))
            ->values()
            ->all();

        $gewaehlt = $request->query('datei');
        if (! is_string($gewaehlt) || ! in_array($gewaehlt, $dateien, true)) {
            $gewaehlt = $dateien[0] ?? null;
        }

        $eintraege = [];
        $groesse = 0;
        $gekuerzt = false;
        if ($gewaehlt !== null) {
            $pfad = $verzeichnis.'/'.$gewaehlt;
            $groesse = (int) filesize($pfad);
            $gekuerzt = $groesse > self::LESE_BYTES;
            $eintraege = $this->parse($this->tail($pfad, self::LESE_BYTES));
        }

        return view('admin.logs.index', [
            'dateien' => $dateien,
            'gewaehlt' => $gewaehlt,
            'eintraege' => $eintraege,
            'groesse' => $groesse,
            'gekuerzt' => $gekuerzt,
            'lese_kb' => (int) (self::LESE_BYTES / 1024),
        ]);
    }

    /** Liest die letzten $bytes einer Datei und verwirft die angebrochene erste Zeile. */
    private function tail(string $pfad, int $bytes): string
    {
        $groesse = (int) filesize($pfad);
        $fh = fopen($pfad, 'rb');
        if ($fh === false) {
            return '';
        }
        if ($groesse > $bytes) {
            fseek($fh, -$bytes, SEEK_END);
            fgets($fh);
        }
        $inhalt = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $inhalt;
    }

    /**
     * Zerlegt Laravel-Logtext in einzelne Eintraege, neueste zuerst.
     *
     * @return list<array{zeit:string, kanal:string, level:string, kopf:string, rest:string}>
     */
    private function parse(string $text): array
    {
        $muster = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s*([\w-]+)\.(\w+):(.*?)(?=^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}|\z)/ms';
        if (! preg_match_all($muster, $text, $treffer, PREG_SET_ORDER)) {
            return [];
        }

        $eintraege = [];
        foreach (array_reverse($treffer) as $t) {
            $koerper = trim($t[4]);
            $zeilen = preg_split('/\r?\n/', $koerper, 2) ?: [];
            $eintraege[] = [
                'zeit' => $t[1],
                'kanal' => $t[2],
                'level' => strtoupper($t[3]),
                'kopf' => trim($zeilen[0] ?? ''),
                'rest' => trim($zeilen[1] ?? ''),
            ];
            if (count($eintraege) >= self::MAX_EINTRAEGE) {
                break;
            }
        }

        return $eintraege;
    }
}
