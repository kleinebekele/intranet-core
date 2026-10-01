<?php

namespace App\Ekkon;

/**
 * Die schmale öffentliche Schnittstelle des Basis-Pakets für Submodule.
 *
 * Submodule sollen NICHT auf die interne Config-Struktur (config('ekkon.mssql.…'))
 * zugreifen – sonst bricht jede Umbenennung dort fremde Pakete. Sie fragen hier.
 */
class Ekkon
{
    /**
     * Name der Laravel-Connection zur MSSQL-Quelle: DB::connection(Ekkon::mssqlConnection()).
     *
     * Der Name ist konfigurierbar, damit er zur jeweiligen Datenquelle passen darf
     * (z. B. "wawi" statt eines nichtssagenden "mssql") – die Tasks des Submoduls
     * bleiben dadurch lesbar.
     */
    public static function mssqlConnection(): string
    {
        return (string) config('ekkon.mssql_connection', 'mssql');
    }

    /**
     * Sind überhaupt Zugangsdaten hinterlegt?
     *
     * Wichtig für Tasks/Seiten, die ohne Verbindung etwas anderes tun (Fallback,
     * Hinweis). Ohne diese Prüfung liefert PDO einen kryptischen Treiberfehler –
     * und wer einen Fallback baut, muss WISSEN, dass er im Fallback ist, sonst
     * zeigt eine funktionierende Seite stillschweigend veraltete Zahlen.
     */
    public static function mssqlKonfiguriert(): bool
    {
        $config = (array) config('ekkon.mssql', []);

        return ($config['odbc_datasource_name'] ?? '') !== '' || ($config['host'] ?? '') !== '';
    }

    /**
     * Antwortet der MSSQL-Server überhaupt? TCP-Verbindung zu Host/Port mit kurzem Zeitlimit -
     * null = erreichbar, sonst der Grund. Gemessen 2026-10-01: Der ODBC Driver 18 ignoriert
     * PDO::ATTR_TIMEOUT und wartet beim Login rund 30 s; dieser Test entscheidet in Sekunden, ob es
     * sich überhaupt lohnt. Ohne erkennbaren Host (z. B. benannte Instanz) gilt er als bestanden.
     */
    public static function mssqlErreichbar(float $sekunden = 3.0): ?string
    {
        $config = (array) config('ekkon.mssql', []);
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 1433);
        $dsn = (string) ($config['odbc_datasource_name'] ?? '');
        if ($dsn !== '' && preg_match('/(?:^|;)\s*(?:Server|Address|Addr)\s*=\s*(?:tcp:)?([^;,]+)(?:,(\d+))?/i', $dsn, $m) === 1) {
            // Benannte Instanz (host\name) ohne festen Port: dynamischer Port, TCP-Test nicht möglich.
            if (str_contains($m[1], '\\') && empty($m[2])) {
                return null;
            }
            $host = trim($m[1]);
            $port = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1433;
        }
        if ($host === '') {
            return null;
        }

        $fehlerNr = 0;
        $fehler = '';
        $socket = @fsockopen($host, $port, $fehlerNr, $fehler, $sekunden);
        if ($socket === false) {
            return "MSSQL-Server {$host}:{$port} antwortet nicht binnen {$sekunden} s (".trim($fehler ?: 'Zeitüberschreitung').').';
        }
        fclose($socket);

        return null;
    }
}
