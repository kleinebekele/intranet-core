<?php

use App\Support\IpKuerzung;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bisher voll gespeicherte IP-Adressen von Personen nachträglich auf die
 * ersten beiden Blöcke kürzen (Datenschutz) – Audit-Log und Protokoll der
 * Microsoft-Anmeldungen. Neue Einträge kürzt App\Support\IpKuerzung schon
 * beim Schreiben. Mehrfach ausführbar: Gekürztes bleibt gekürzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['audit_log', 'microsoft_anmeldungen'] as $tabelle) {
            if (! Schema::hasTable($tabelle) || ! Schema::hasColumn($tabelle, 'ip')) {
                continue;
            }

            DB::table($tabelle)->whereNotNull('ip')->select('ip')->distinct()->pluck('ip')
                ->each(function (string $ip) use ($tabelle): void {
                    $kurz = IpKuerzung::kuerzen($ip);
                    if ($kurz !== $ip) {
                        DB::table($tabelle)->where('ip', $ip)->update(['ip' => $kurz]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Gekürzte Adressen lassen sich nicht zurückholen.
    }
};
