<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * A registered module. Rows are created/updated by `php artisan modules:sync`.
 * `position` and `is_enabled` are owned by the admin and never overwritten
 * by a re-sync.
 */
class Module extends Model
{
    protected $fillable = ['key', 'name', 'icon', 'position', 'is_enabled'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'position' => 'integer',
    ];

    public function menuItems(): HasMany
    {
        return $this->hasMany(ModuleMenuItem::class)->orderBy('position');
    }

    /**
     * Where clicking the module in the main sidebar should take you:
     * its first sub-page (the module's landing page).
     */
    public function homeUrl(): ?string
    {
        $first = $this->menuItems->first();

        return $first && Route::has($first->route_name)
            ? route($first->route_name)
            : null;
    }
}
