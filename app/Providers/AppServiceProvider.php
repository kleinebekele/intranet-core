<?php

namespace App\Providers;

use App\Listeners\AnmeldungenProtokollieren;
use App\Listeners\MailInDieOutbox;
use App\Mail\Vorlagen\VorlagenRegister;
use App\Models\Setting;
use App\Modules\Support\ModuleRegistry;
use App\Support\Mailausloeser;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared registry that every module provider pushes its manifest into.
        // singletonIf so that a module provider (which registers earlier) can
        // create it first without us later replacing it with an empty one.
        $this->app->singletonIf(ModuleRegistry::class);

        // Mailvorlagen-Register: Singleton, damit Module später eigene Vorlagen
        // anmelden können (wie beim Modul-Register).
        $this->app->singletonIf(VorlagenRegister::class);
    }

    /**
     * `schedule:run` darf nie die FALSCHE Minute auswerten.
     *
     * Cron weckt den Scheduler genau auf der Minutengrenze. Liest PHP die Uhr
     * dabei noch als Sekunde 59 der Vorminute (gesehen 2026-09-21 auf einem
     * Server: Läufe um hh:mm:59, ein 10-Minuten-Task mit Startzeit :01:00,
     * ganze Schlitze ohne Lauf), wertet Laravel die Vorminute aus: deren Tasks laufen
     * ein zweites Mal, die der neuen Minute fallen aus.
     *
     * Deshalb: Startet `schedule:run` in den letzten Sekunden einer Minute,
     * kurz warten, bis die neue wirklich begonnen hat. Kostet nur im
     * betroffenen Fall etwas (unter einer Sekunde) und braucht keine Änderung
     * an der Crontab. Muss HIER passieren und nicht im Befehl: Laravel merkt
     * sich die Startzeit schon im Konstruktor von ScheduleRunCommand – der
     * läuft erst nach den Providern.
     */
    private function minutengrenzeAbwarten(): void
    {
        if (! $this->app->runningInConsole() || (($_SERVER['argv'][1] ?? '') !== 'schedule:run')) {
            return;
        }

        $jetzt = microtime(true);
        $sekunde = $jetzt - floor($jetzt / 60) * 60; // Zeitzonen-Versatz ist immer ganze Minuten

        if ($sekunde >= 55.0) {
            usleep((int) ((60.0 - $sekunde + 0.05) * 1_000_000));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->minutengrenzeAbwarten();

        // Hinter TLS-Terminierung (nginx/Proxy) erkennt Laravel https nicht
        // immer selbst. Im Produktivbetrieb Links/Assets zwingend als https
        // erzeugen, sonst blockiert der Browser CSS/JS als „Mixed Content".
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Feed the left sidebar with the current navigation state.
        View::composer('layouts.sidebar', NavigationComposer::class);

        // Jede ausgehende Mail in den Ausgangskorb umleiten (Drosselung +
        // Protokoll). Bewusst hier und nicht per Auto-Discovery: der Listener
        // greift so tief in den Versand ein, dass man ihn sehen soll.
        Event::listen(MessageSending::class, MailInDieOutbox::class);

        // Anmelde-Ereignisse ins Audit-Log (Verwaltung → Audit) und
        // „zuletzt angemeldet" am Benutzer. Ebenfalls bewusst sichtbar
        // verdrahtet.
        Event::listen(Login::class, [AnmeldungenProtokollieren::class, 'angemeldet']);
        Event::listen(Logout::class, [AnmeldungenProtokollieren::class, 'abgemeldet']);
        Event::listen(Lockout::class, [AnmeldungenProtokollieren::class, 'ausgesperrt']);
        Event::listen(Registered::class, [AnmeldungenProtokollieren::class, 'registriert']);
        Event::listen(PasswordReset::class, [AnmeldungenProtokollieren::class, 'passwortZurueckgesetzt']);

        // Die Core-Vorlagen als Auslöser anmelden, damit man ihren Absender
        // einstellen kann, bevor die erste solche Mail rausgegangen ist. Lazy:
        // die Liste wird erst gebaut, wenn die Konfig-Seite sie abfragt.
        Mailausloeser::anbieten(function () {
            return array_map(
                fn ($d) => ['modul' => $d->modul, 'ausloeser' => $d->ausloeser()],
                array_values(app(VorlagenRegister::class)->versendbare()),
            );
        });

        // Das Mail-Stundenlimit kommt ausschließlich aus der Verwaltung – es
        // hängt am Vertrag des Mailproviders, nicht am Server. Ohne Eintrag
        // bleibt es beim Standard aus config/mail.php: kein Limit.
        if (filled($limit = Setting::get('mail_stundenlimit'))) {
            config(['mail.outbox.stundenlimit' => (int) $limit]);
        }
    }
}
