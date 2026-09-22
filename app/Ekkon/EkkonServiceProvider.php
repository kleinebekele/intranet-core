<?php

namespace App\Ekkon;

use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use App\Ekkon\Console\RunTaskCommand;
use App\Ekkon\Console\TeamsLauschenCommand;
use App\Ekkon\Console\TimeoutTestCommand;
use App\Ekkon\Models\Notification;
use App\Ekkon\Models\TaskRun;
use App\Ekkon\Support\TaskRegistry;

/**
 * Ekkon: Task-System + Benachrichtigungen + Webhook-Eingang – fester
 * Bestandteil des Cores (bis 2026-09 das Paket `do1emu/module-ekkon`).
 *
 * Enthält bewusst KEINE Fachlogik. Tasks steuern Module bei, indem ihr
 * Provider sein Tasks-Verzeichnis per TaskRegistry::addSource() anmeldet.
 *
 * Nach außen tritt Ekkon weiter als Modul `ekkon` auf: Menüpunkte, Rechte,
 * sprechende Adressen und die Routen `module.ekkon.*` bleiben, wie sie sind.
 */
class EkkonServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        $manifest = ModuleManifest::make('ekkon', 'Ekkon', icon: 'cog');

        // Kein Paketverzeichnis: die Migrationen liegen beim Core. Ohne diese
        // Zeile nähme die Basisklasse das Core-Wurzelverzeichnis an und schriebe
        // ALLE Core-Migrationen dem Modul „ekkon" zu.
        $manifest->basePath = app_path('Ekkon');
        $manifest->fest = true;

        return $manifest
            ->item('index', 'Aufgaben', 'module.ekkon.index')
            // Betriebswerkzeug wie die Aufgaben: Die Route trägt hart
            // EnsureUserIsAdmin (Webhook-URLs sind Passwörter). Neuer Menüpunkt
            // startet ohne Rollen = nur Admin – passt.
            ->item('notifications', 'Benachrichtigungen', 'module.ekkon.notifications.index')
            ->item('webhooks', 'Webhook-Eingang', 'module.ekkon.webhooks.index')
            // Teams-Chat-Eingang: was andere dem Bot-Konto schreiben (Lauscher).
            ->item('teams', 'Teams-Chat', 'module.ekkon.teams.index');
    }

    /**
     * Steht in einem veralteten Paket-Cache (`bootstrap/cache/packages.php`) noch
     * der Provider des alten Pakets, landet Laravel über den Altnamen ein zweites
     * Mal hier. Der Nachzügler tut nichts – sonst gäbe es jede Route und jeden
     * Task doppelt.
     */
    private bool $fuehrt = false;

    public function register(): void
    {
        // Das alte Paket liegt noch mit Code in vendor/ und führt – siehe Altnamen.
        if (Altnamen::altesPaketAktiv() || $this->app->bound('ekkon.im-core')) {
            return;
        }

        $this->app->instance('ekkon.im-core', true);
        $this->fuehrt = true;

        parent::register();

        // Die Registry ist schon gebunden – unter dem neuen und dem alten Namen,
        // noch vor dem ersten Provider (Altnamen::registryBinden in bootstrap/app.php).
        // Paket-Provider haben ihre Tasks hier oft längst angemeldet.
        $this->app->singletonIf(TaskRegistry::class);

        $this->app->make(TaskRegistry::class)->addSource(
            app_path('Ekkon/Tasks'),
            __NAMESPACE__.'\\Tasks',
            'core',
            TaskRegistry::SYSTEM,
        );

        // Die MSSQL-Quelle als Laravel-Connection. Der Name kommt aus der Config,
        // damit er zur Datenquelle passen darf (z. B. "wawi").
        config(['database.connections.'.Ekkon::mssqlConnection() => config('ekkon.mssql')]);
    }

    public function boot(): void
    {
        if (! $this->fuehrt) {
            return;
        }

        // Nicht parent::boot(): Routen und Views liegen an den Core-Orten, die
        // Migrationen zwischen denen des Cores (gleiche Dateinamen wie im alten
        // Paket – auf bestehenden Instanzen gelten sie damit als gelaufen).
        $this->loadRoutesFrom(base_path('routes/ekkon.php'));
        $this->loadViewsFrom(resource_path('views/ekkon'), 'ekkon');

        // Für JEDE Meldungsart eine bearbeitbare Mailvorlage im Core-Register
        // anmelden (`ekkon:<meldungsart>`). Läuft in Web UND Konsole: im Web für
        // die Bearbeitung unter Verwaltung → Mailvorlagen, in der Konsole für den
        // Versand durch SendNotifications.
        $this->benachrichtigungsVorlagenAnmelden();
        $this->hinweiseAnmelden();

        // Teams-Chat: Eingehende Nachrichten an das Bot-Konto beantwortet die KI
        // (Ekkon → Teams-Chat → KI-Einstellungen). Der Listener prüft selbst, ob
        // die KI eingeschaltet ist.
        \Illuminate\Support\Facades\Event::listen(
            \App\Ekkon\Events\TeamsNachrichtEmpfangen::class,
            \App\Ekkon\Listeners\KiAntwortet::class,
        );

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([RunTaskCommand::class, TimeoutTestCommand::class, TeamsLauschenCommand::class]);

        // Jeden aktiven Task beim Laravel-Scheduler anmelden. Der Server braucht
        // dafür nur EINEN Cron-Eintrag: * * * * * php artisan schedule:run
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Sicherheitsschalter: ohne EKKON_TASKS_ENABLED=true wird gar nichts
            // eingeplant (zweite Sperre sitzt im TaskRunner).
            if (! config('ekkon.tasks_enabled')) {
                return;
            }

            $registry = $this->app->make(TaskRegistry::class);

            foreach ($registry->all() as $task) {
                // Modul in der Modulverwaltung deaktiviert → seine Tasks ruhen.
                // `schedule:run` startet jede Minute frisch, ein Umschalten wirkt
                // also binnen einer Minute (zweite Sperre sitzt im TaskRunner).
                if (! $registry->modulAktiv($task->key())) {
                    continue;
                }

                $schedule->command('ekkon:task', [$task->key()])
                    ->cron($task->schedule())
                    ->withoutOverlapping($task->lockSeconds() / 60)
                    ->runInBackground();
            }

            // Lauf-Historie begrenzen (Statistik bleibt aussagekräftig, DB schlank).
            $schedule->call(function (): void {
                TaskRun::query()->where('started_at', '<', now()->subDays(14))->delete();
            })->dailyAt('04:15');
        });
    }

    /**
     * Liegengebliebene Benachrichtigungen an die Glocke in der Kopfzeile melden
     * (Core ab 2026-09-09, `App\Support\Hinweise`). Zwei Zustände, die sonst nur
     * im Log und auf der Benachrichtigungs-Seite auffallen:
     *  - failed    – drei Zustellversuche erfolglos (z. B. Teams-Workflow gelöscht),
     *  - ohne_ziel – Meldungsart ohne aktive Route, niemand wurde informiert.
     * Nur für Admins – die Seite dahinter ist ohnehin Admin-only. Ein älterer Core
     * ohne die Klasse bekommt schlicht keine Glocke.
     */
    private function hinweiseAnmelden(): void
    {
        if (! class_exists(\App\Support\Hinweise::class)) {
            return;
        }

        \App\Support\Hinweise::anbieten(function (\App\Models\User $user): array {
            if (! $user->isAdmin()) {
                return [];
            }

            $url = route('module.ekkon.notifications.index');
            $quelle = 'Ekkon · Benachrichtigungen';
            $hinweise = [];

            $failed = Notification::query()->where('status', 'failed')->count();
            if ($failed > 0) {
                $hinweise[] = new \App\Support\Hinweis(
                    $failed.' Benachrichtigung(en) nicht zugestellt – Zustellweg prüfen',
                    $url,
                    $quelle,
                    $failed,
                );
            }

            $ohneZiel = Notification::query()->where('status', 'ohne_ziel')->count();
            if ($ohneZiel > 0) {
                $hinweise[] = new \App\Support\Hinweis(
                    $ohneZiel.' Meldung(en) ohne Route – niemand wurde informiert',
                    $url,
                    $quelle,
                    $ohneZiel,
                );
            }

            return $hinweise;
        });
    }

    /**
     * Je deklarierter Meldungsart eine Mailvorlage im Core-Register anmelden.
     *
     * Erst wenn alle Provider geladen sind (`booted`), stehen alle Tasks und
     * damit alle Meldungsarten fest. Ohne das Core-Mailvorlagen-System (älterer
     * Core) passiert nichts – die Ekkon-Mails fallen dann auf reinen Text
     * zurück (siehe SendNotifications).
     */
    private function benachrichtigungsVorlagenAnmelden(): void
    {
        if (! class_exists(\App\Mail\Vorlagen\VorlagenRegister::class)) {
            return;
        }

        $this->app->booted(function (): void {
            $register = $this->app->make(\App\Mail\Vorlagen\VorlagenRegister::class);
            $arten = $this->app->make(TaskRegistry::class)->meldungsarten();

            foreach ($arten as $art => $klartext) {
                $register->registrieren(new \App\Mail\Vorlagen\VorlagenDefinition(
                    schluessel: 'ekkon:'.$art,
                    titel: 'Benachrichtigung: '.$klartext,
                    beschreibung: 'Mail für die Ekkon-Meldungsart „'.$klartext.'". Wird verschickt, '
                        .'wenn dafür eine Mail-Route existiert. Der Text kommt vom auslösenden Task '
                        .'(Platzhalter {{ text }}).',
                    platzhalter: [
                        // Bewusst NICHT „titel": das ist im Rahmen der Haupttitel
                        // der Instanz – die Namen würden kollidieren.
                        'ueberschrift' => 'Überschrift/Betreff der Meldung',
                        'text' => 'Der Meldungstext vom Task (kann einen Link enthalten)',
                        'quelle' => 'Auslösender Task',
                    ],
                    betreff: '{{ ueberschrift }}',
                    html: self::MELDUNG_HTML,
                    text: self::MELDUNG_TEXT,
                ));
            }
        });
    }

    private const MELDUNG_HTML = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;font-weight:bold;">{{ ueberschrift }}</p>
<div style="margin:0 0 16px;white-space:pre-line;color:#374151;">{{ text }}</div>
<p style="margin:0;color:#9ca3af;font-size:12px;">Ausgelöst von: {{ quelle }}</p>
HTML;

    private const MELDUNG_TEXT = <<<'TEXT'
{{ ueberschrift }}

{{ text }}

—
Ausgelöst von: {{ quelle }}
TEXT;
}
