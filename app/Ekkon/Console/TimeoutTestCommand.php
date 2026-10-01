<?php

namespace App\Ekkon\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Ekkon\Ekkon;
use PDO;
use Throwable;

/**
 * Beweist (oder widerlegt), dass das Abfrage-Zeitlimit der MSSQL-Verbindung
 * wirklich greift.
 *
 * ── Warum es diesen Befehl gibt ─────────────────────────────────────────
 * Nach dem Vorfall am 2026-08-05 (eine Abfrage lief 67 Minuten und nahm die
 * produktive Wawi mit) steht in config/ekkon.php ein PDO::ATTR_TIMEOUT. Ob
 * PDO_ODBC diesen Wert als ABFRAGE-Zeitlimit durchreicht oder nur als
 * Verbindungs-Zeitlimit, ist treiberabhaengig - es haengt am ODBC-Treiber und
 * an der PHP-Fassung.
 *
 * Eine Schutzmassnahme, von der man nur GLAUBT, dass sie wirkt, ist
 * gefaehrlicher als gar keine: Man verlaesst sich darauf und schaut nicht mehr
 * hin. Deshalb wird sie hier gemessen statt angenommen.
 *
 * Der Test schickt ein absichtlich langsames WAITFOR DELAY und prueft, ob die
 * Datenbank vorher abbricht. Er veraendert nichts.
 */
class TimeoutTestCommand extends Command
{
    protected $signature = 'ekkon:timeout-test {--sekunden=5 : Zeitlimit fuer diesen Test}
                                               {--warten=30 : So lange soll die Testabfrage kuenstlich brauchen}
                                               {--login : Statt der Abfrage das LOGIN-Zeitlimit messen (Verbindung zu einer unerreichbaren Adresse)}
                                               {--zusatz= : Beim Login-Test zusätzlich an den DSN hängen, z. B. "ConnectRetryCount=0"}
                                               {--tcp : TCP-Vorabtest (Ekkon::mssqlErreichbar) gegen die echte Wawi und eine tote Adresse messen}';

    protected $description = 'Prueft, ob das Abfrage-Zeitlimit der MSSQL-Verbindung wirklich greift (schreibt nichts).';

    public function handle(): int
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            $this->error('Keine MSSQL-Verbindung konfiguriert (MSSQL_ODBC_DSN).');

            return self::FAILURE;
        }

        if ($this->option('login')) {
            return $this->loginTest();
        }
        if ($this->option('tcp')) {
            return $this->tcpTest();
        }

        $limit = max(1, (int) $this->option('sekunden'));
        $warten = max(1, (int) $this->option('warten'));

        if ($warten <= $limit) {
            $this->error('--warten muss groesser sein als --sekunden, sonst beweist der Test nichts.');

            return self::FAILURE;
        }

        $this->info("Zeitlimit: {$limit}s · Testabfrage braucht: {$warten}s");
        $this->line('Erwartung: Die Abfrage bricht nach etwa '.$limit.' Sekunden mit einem Fehler ab.');
        $this->newLine();

        // Eigene Verbindung mit dem Testwert - die echte 'wawi'-Verbindung
        // bleibt unangetastet.
        $config = config('ekkon.mssql');
        $config['options'] = [PDO::ATTR_TIMEOUT => $limit];
        config(['database.connections.ekkon-timeout-test' => $config]);

        $start = microtime(true);

        try {
            DB::connection('ekkon-timeout-test')
                ->statement("WAITFOR DELAY '00:00:".str_pad((string) $warten, 2, '0', STR_PAD_LEFT)."'");

            $dauer = microtime(true) - $start;

            $this->newLine();
            $this->error(sprintf(
                'DAS ZEITLIMIT GREIFT NICHT. Die Abfrage lief %.1f Sekunden durch.',
                $dauer,
            ));
            $this->line('Folge: PDO::ATTR_TIMEOUT in config/ekkon.php ist auf diesem Server wirkungslos.');
            $this->line('Eine einzelne entgleiste Abfrage kann die Datenbank weiterhin blockieren - der Schutz');
            $this->line('beschraenkt sich dann auf die Laufzeit-Warnung des TaskRunners.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $dauer = microtime(true) - $start;

            // Kurz vor dem Limit abgebrochen = der Treiber hat es umgesetzt.
            // Grosszuegige Grenze, weil Verbindungsaufbau und Latenz dazukommen.
            if ($dauer < $warten * 0.9) {
                $this->newLine();
                $this->info(sprintf('Das Zeitlimit greift: Abbruch nach %.1f Sekunden.', $dauer));
                $this->line('Meldung: '.mb_substr($e->getMessage(), 0, 200));

                return self::SUCCESS;
            }

            $this->newLine();
            $this->error(sprintf(
                'Abbruch erst nach %.1f Sekunden - das war nicht das Zeitlimit, sondern die Abfrage selbst.',
                $dauer,
            ));
            $this->line('Meldung: '.mb_substr($e->getMessage(), 0, 200));

            return self::FAILURE;
        }
    }

    /**
     * Login-Zeitlimit messen (2026-10-01): dieselbe Verbindung samt config/ekkon.php-Optionen, nur mit
     * einer Server-Adresse, die nie antwortet. Bricht der Aufbau nach etwa MSSQL_LOGIN_TIMEOUT Sekunden
     * ab, wirkt das Limit. Fasst die echte Wawi nicht an.
     */
    /**
     * TCP-Vorabtest pruefen, BEVOR der Runner sich darauf verlaesst (Emanuel 2026-10-01): gegen die echte
     * Wawi muss er sofort „erreichbar" melden, gegen 10.255.255.1 nach etwa 3 s „nicht erreichbar".
     * Fasst die Wawi nur mit einem TCP-Verbindungsaufbau an, keine Anmeldung, keine Abfrage.
     */
    private function tcpTest(): int
    {
        $ok = true;

        $t = microtime(true);
        $echt = Ekkon::mssqlErreichbar();
        $dauer = microtime(true) - $t;
        $this->line(sprintf('Echte Wawi: %s (%.2f s)', $echt ?? 'erreichbar', $dauer));
        if ($echt !== null) {
            $this->error('Der Test haelt die echte Wawi fuer nicht erreichbar - so darf er NICHT in den Runner.');
            $ok = false;
        }

        $original = config('ekkon.mssql');
        $tot = $original;
        if (($tot['odbc_datasource_name'] ?? '') !== '') {
            $tot['odbc_datasource_name'] = preg_replace('/Server=[^;]*/i', 'Server=10.255.255.1,1433', (string) $tot['odbc_datasource_name']);
        } else {
            $tot['host'] = '10.255.255.1';
        }
        config(['ekkon.mssql' => $tot]);
        $t = microtime(true);
        $weg = Ekkon::mssqlErreichbar();
        $dauer = microtime(true) - $t;
        config(['ekkon.mssql' => $original]);
        $this->line(sprintf('Tote Adresse: %s (%.2f s)', $weg ?? 'erreichbar?!', $dauer));
        if ($weg === null || $dauer > 5) {
            $this->error('Gegen die tote Adresse meldet der Test nicht schnell genug „nicht erreichbar".');
            $ok = false;
        }

        if ($ok) {
            $this->info('TCP-Vorabtest verhaelt sich wie erwartet.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function loginTest(): int
    {
        $config = config('ekkon.mssql');
        $dsn = (string) ($config['odbc_datasource_name'] ?? '');
        if ($dsn === '') {
            $this->error('Der Login-Test gilt nur fuer den ODBC-Weg (MSSQL_ODBC_DSN).');

            return self::FAILURE;
        }
        $limit = (int) ($config['options'][PDO::ATTR_TIMEOUT] ?? 0);
        // 10.255.255.1 ist nicht routbar: kein Abweisen, nur Schweigen - wie ein ausgefallener Server.
        $config['odbc_datasource_name'] = preg_replace('/Server=[^;]*/i', 'Server=10.255.255.1,1433', $dsn) ?? $dsn;
        $zusatz = trim((string) $this->option('zusatz'), " ;");
        if ($zusatz !== '') {
            $config['odbc_datasource_name'] = rtrim($config['odbc_datasource_name'], ';').';'.$zusatz;
            $this->line('DSN-Zusatz: '.$zusatz);
        }
        config(['database.connections.ekkon-login-test' => $config]);

        $this->info("Login-Zeitlimit laut config/ekkon.php: {$limit}s");
        $start = microtime(true);
        try {
            DB::connection('ekkon-login-test')->getPdo();
            $this->error('Unerwartet: Verbindung zu 10.255.255.1 kam zustande.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $dauer = microtime(true) - $start;
            $this->line(sprintf('Abbruch nach %.1f Sekunden: %s', $dauer, mb_substr($e->getMessage(), 0, 160)));
            if ($limit > 0 && $dauer <= $limit + 5) {
                $this->info('Das Login-Zeitlimit greift.');

                return self::SUCCESS;
            }
            $this->error('Das Login-Zeitlimit greift NICHT wie eingestellt.');

            return self::FAILURE;
        }
    }
}
