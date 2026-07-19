<?php

use App\Http\Controllers\Admin\MailOutboxController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// The intranet has no public landing page: send visitors straight to the
// dashboard (which redirects to the login screen when not signed in).
Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Zwei-Faktor-Abfrage nach dem Passwort-Login.
    Route::get('/two-factor', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify');
    Route::post('/two-factor/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA-Verwaltung im eigenen Profil (Opt-in + TOTP).
    Route::post('/profile/two-factor/enable', [TwoFactorController::class, 'enable'])->name('profile.two-factor.enable');
    Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'setup'])->name('profile.two-factor.setup');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::get('/profile/two-factor/cancel', [TwoFactorController::class, 'cancel'])->name('profile.two-factor.cancel');
    Route::delete('/profile/two-factor/totp', [TwoFactorController::class, 'removeTotp'])->name('profile.two-factor.remove-totp');

    // Admin panel: arrange the module navigation.
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        // Einstellungen: Erscheinungsbild und Betriebsgrenzen.
        Route::get('einstellungen', [SettingController::class, 'index'])->name('settings.index');
        Route::put('einstellungen', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');
        Route::post('/modules/{module}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
        Route::post('/modules/{module}/menu/reorder', [ModuleController::class, 'reorderItems'])->name('modules.menu.reorder');
        Route::put('/modules/{module}/visibility', [ModuleController::class, 'visibility'])->name('modules.visibility');

        // Rollen-Verwaltung (CRUD). {role} bindet automatisch über role_id.
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::post('roles/{role}/detach-all', [RoleController::class, 'detachAll'])->name('roles.detach-all');

        // Benutzer-Verwaltung (CRUD) + Passwort-Reset-Link.
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset', [UserController::class, 'sendReset'])->name('users.reset');
        Route::post('users/{user}/reset-totp', [UserController::class, 'resetTotp'])->name('users.reset-totp');

        // Mail-Ausgangskorb: Versand-Protokoll und Warteschlange.
        Route::get('mails', [MailOutboxController::class, 'index'])->name('mail.index');
        Route::post('mails/{mail}/erneut', [MailOutboxController::class, 'erneut'])->name('mail.erneut');
    });
});

require __DIR__.'/auth.php';
