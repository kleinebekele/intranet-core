<?php

namespace App\Providers;

use App\Listeners\AnmeldungenProtokollieren;
use App\Listeners\MailInDieOutbox;
use App\Mail\Vorlagen\VorlagenRegister;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\Modulzugriff;
use App\Modules\Support\Zugriffsstufe;
use App\Support\Hinweise;
use App\Support\Mailausloeser;
use App\Support\SchedulerTakt;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Blade;
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
        if (! $this->istScheduleRun()) {
            return;
        }

        $jetzt = microtime(true);
        $sekunde = $jetzt - floor($jetzt / 60) * 60; // Zeitzonen-Versatz ist immer ganze Minuten

        if ($sekunde >= 55.0) {
            usleep((int) ((60.0 - $sekunde + 0.05) * 1_000_000));
        }
    }

    /**
     * Pro Minute nur EIN `schedule:run`. Holt Cron eine verpasste Minute nach,
     * startet er den Scheduler zweimal in derselben Sekunde (RAV, 2026-10-21) –
     * der zweite Start beendet sich hier, bevor er irgendetwas einplant. Muss nach
     * {@see minutengrenzeAbwarten()} laufen, damit die belegte Minute die ist,
     * die Laravel gleich auswertet. Details: {@see SchedulerTakt}.
     */
    private function schedulerMinuteBelegen(): void
    {
        if (! $this->istScheduleRun() || SchedulerTakt::belegen()) {
            return;
        }

        fwrite(STDERR, "schedule:run fuer diese Minute laeuft bereits - zweiter Start beendet.\n");
        exit(0);
    }

    private function istScheduleRun(): bool
    {
        return $this->app->runningInConsole() && (($_SERVER['argv'][1] ?? '') === 'schedule:run');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->minutengrenzeAbwarten();
        $this->schedulerMinuteBelegen();

        // Glocke: Cron steht oder läuft unregelmäßig (Doppelstarts, ausgefallene
        // Minuten) – sonst merkt man es erst an doppelten Mails oder fehlenden Läufen.
        Hinweise::anbieten(fn (User $user): array => $user->isAdmin()
            ? SchedulerTakt::hinweise(route('module.ekkon.index').'#cron-takt')
            : []);

        // Hinter TLS-Terminierung (nginx/Proxy) erkennt Laravel https nicht
        // immer selbst. Im Produktivbetrieb Links/Assets zwingend als https
        // erzeugen, sonst blockiert der Browser CSS/JS als „Mixed Content".
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            // Links in Mails (Passwort-Reset, Bestätigungen) immer auf APP_URL
            // bauen, nie aus dem Host-Header der Anfrage – sonst könnte ein
            // Fremder einen echten Reset-Link auf seine Domain umlenken.
            if ($root = config('app.url')) {
                URL::forceRootUrl($root);
            }
        }

        // Reverse-Proxy vor dem Intranet, der die Client-IP nur im Header
        // X-Forwarded-For mitgibt (statt per nginx real_ip selbst einzusetzen):
        // dessen IP(s) in TRUSTED_PROXIES. Nur diesen Absendern glaubt Laravel
        // den Header – sonst ließe sich die IP (Terminal-Freigaben, Drosseln,
        // Audit) einfach vorgeben.
        if ($proxies = config('intranet.trusted_proxies')) {
            TrustProxies::at($proxies);
        }

        // Knöpfe nach Zugriffsstufe ausblenden: @darf('bearbeiten') … @enddarf
        // (Stufe der aktuellen Modulseite, gesetzt von EnsureModuleAccess).
        Blade::if('darf', fn (Zugriffsstufe|string $stufe) => Zugriffsstufe::darf($stufe));
        // Genauer: ginge ein Aufruf GENAU dieser Route durch? @darfRoute('module.x.dishes.destroy') … @enddarfRoute
        Blade::if('darfRoute', fn (string $routeName) => app(Modulzugriff::class)->darfRoute($routeName));

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
