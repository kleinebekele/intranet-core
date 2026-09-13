<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ausgangskorb leeren. Jede Minute, damit eilige Mails (2FA-Code, Passwort-Link)
// höchstens eine Minute warten. withoutOverlapping, damit sich zwei Läufe bei
// langsamem Mailserver nicht in die Quere kommen und Mails doppelt rausgehen.
Schedule::command('mail:ausliefern')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Audit-Log aufräumen: Einträge älter als AUDIT_AUFBEWAHRUNG_TAGE (0 = nie).
Schedule::call(function () {
    $tage = (int) config('intranet.audit_aufbewahrung_tage');
    if ($tage > 0) {
        \App\Models\AuditEintrag::query()->where('created_at', '<', now()->subDays($tage))->delete();
    }
})->name('audit:aufraeumen')->dailyAt('03:30');
