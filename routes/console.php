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
