<?php

use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// The intranet has no public landing page: send visitors straight to the
// dashboard (which redirects to the login screen when not signed in).
Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin panel: arrange the module navigation.
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');
        Route::post('/modules/{module}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
        Route::post('/modules/{module}/menu/reorder', [ModuleController::class, 'reorderItems'])->name('modules.menu.reorder');

        // Rollen-Verwaltung (CRUD). {role} bindet automatisch über role_id.
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::post('roles/{role}/detach-all', [RoleController::class, 'detachAll'])->name('roles.detach-all');

        // Benutzer-Verwaltung (CRUD) + Passwort-Reset-Link.
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset', [UserController::class, 'sendReset'])->name('users.reset');
    });
});

require __DIR__.'/auth.php';
