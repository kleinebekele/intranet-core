<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use App\Ekkon\Http\Controllers\NotificationController;
use App\Ekkon\Http\Controllers\TaskController;
use App\Ekkon\Http\Controllers\TeamsEingangController;
use App\Ekkon\Http\Controllers\WebhookController;

Route::middleware(['web', 'auth'])
    ->prefix('modules/ekkon')
    ->name('module.ekkon.')
    ->group(function (): void {
        // Task-System: bewusst HART nur für Administratoren (Betriebswerkzeug) —
        // unabhängig davon, was in der Modul-Verwaltung eingestellt wird.
        Route::middleware(EnsureUserIsAdmin::class)->group(function (): void {
            Route::get('/', [TaskController::class, 'index'])->name('index');
            Route::get('/task/{group}/{name}', [TaskController::class, 'show'])->name('task.show');
            Route::post('/task/{group}/{name}/run', [TaskController::class, 'run'])->name('task.run');
            Route::post('/task/{group}/{name}/toggle', [TaskController::class, 'toggle'])->name('task.toggle');
            Route::post('/task/{group}/{name}/einstellungen', [TaskController::class, 'einstellungen'])->name('task.einstellungen');

            // Benachrichtigungen: Channels sind Passwort-Träger (Webhook-URL),
            // und wer routet, entscheidet, wer Betriebsmeldungen sieht –
            // gehört also zum Betriebswerkzeug, nicht in die Rollen-Freigabe.
            Route::prefix('benachrichtigungen')->name('notifications.')->group(function (): void {
                Route::get('/', [NotificationController::class, 'index'])->name('index');

                Route::post('/channel', [NotificationController::class, 'channelStore'])->name('channel.store');
                Route::put('/channel/{channel}', [NotificationController::class, 'channelUpdate'])->name('channel.update');
                Route::post('/channel/{channel}/test', [NotificationController::class, 'channelTest'])->name('channel.test');
                Route::post('/channel/{channel}/toggle', [NotificationController::class, 'channelToggle'])->name('channel.toggle');
                Route::delete('/channel/{channel}', [NotificationController::class, 'channelDestroy'])->name('channel.destroy');

                // Microsoft-Konto für den Graph-Weg (Chat-ID statt Webhook).
                // Die Callback-Adresse muss in der Entra-App als Umleitungs-URI stehen.
                Route::post('/microsoft/verbinden', [NotificationController::class, 'graphVerbinden'])->name('graph.verbinden');
                Route::get('/microsoft/callback', [NotificationController::class, 'graphCallback'])->name('graph.callback');
                Route::delete('/microsoft', [NotificationController::class, 'graphTrennen'])->name('graph.trennen');
                Route::get('/microsoft/zugriffe', [NotificationController::class, 'graphZugriffe'])->name('graph.zugriffe');
                Route::get('/microsoft/zugriffe/ordner', [NotificationController::class, 'graphOrdner'])->name('graph.ordner');
                Route::get('/microsoft/zugriffe.json', [NotificationController::class, 'graphZugriffeJson'])->name('graph.zugriffe.json');

                Route::post('/route', [NotificationController::class, 'routeStore'])->name('route.store');
                Route::post('/route/{route}/toggle', [NotificationController::class, 'routeToggle'])->name('route.toggle');
                Route::delete('/route/{route}', [NotificationController::class, 'routeDestroy'])->name('route.destroy');

                Route::post('/{notification}/retry', [NotificationController::class, 'retry'])->name('retry');
                Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
            });

            // Teams-Chat-Eingang (Admin-Seite): was andere dem Bot-Konto schreiben.
            Route::prefix('teams')->name('teams.')->group(function (): void {
                Route::get('/', [TeamsEingangController::class, 'index'])->name('index');
                Route::post('/ki', [TeamsEingangController::class, 'kiSpeichern'])->name('ki.speichern');
                Route::get('/ki/modelle', [TeamsEingangController::class, 'kiModelle'])->name('ki.modelle');
                Route::post('/ki/test', [TeamsEingangController::class, 'kiTest'])->name('ki.test');
                Route::post('/{nachricht}/antworten', [TeamsEingangController::class, 'antworten'])->name('antworten');
                Route::delete('/{nachricht}', [TeamsEingangController::class, 'destroy'])->name('destroy');
            });

            // Webhook-Eingang (Admin-Seite): Schlüssel sind Passwörter.
            Route::prefix('webhooks')->name('webhooks.')->group(function (): void {
                Route::get('/', [WebhookController::class, 'index'])->name('index');
                Route::post('/quelle', [WebhookController::class, 'quelleStore'])->name('quelle.store');
                Route::post('/quelle/{quelle}/toggle', [WebhookController::class, 'quelleToggle'])->name('quelle.toggle');
                Route::put('/quelle/{quelle}', [WebhookController::class, 'quelleUpdate'])->name('quelle.update');
                Route::delete('/quelle/{quelle}', [WebhookController::class, 'quelleDestroy'])->name('quelle.destroy');
                Route::delete('/eingang/{eingang}', [WebhookController::class, 'eingangDestroy'])->name('eingang.destroy');
                Route::post('/eingang/{eingang}/erneut', [WebhookController::class, 'eingangErneut'])->name('eingang.erneut');
            });
        });
    });

// Öffentlicher Empfang: bewusst OHNE 'web' (keine Session, kein CSRF) – der
// Absender ist ein fremder Dienst. Der Schlüssel in der URL ist die Zugangsprüfung,
// die Drossel fängt Dauerfeuer ab. Name ohne 'module.'-Präfix, damit die
// Modul-Zugriffsprüfung des Cores hier nicht greift. Die Drossel ist bewusst
// weit: Carrier-Push-Dienste (DHL) schicken je Ereignis eine Nachricht und
// morgens in Wellen; ein 429 kostet dort eine Stunde Wiederholung.
Route::post('/webhooks/ekkon/{schluessel}', [WebhookController::class, 'empfangen'])
    ->middleware('throttle:600,1')
    ->name('ekkon.webhook.empfangen');
