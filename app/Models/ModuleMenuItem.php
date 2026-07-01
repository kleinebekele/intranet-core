<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

/**
 * One sub-page of a module in the left menu. Order is owned by the admin.
 */
class ModuleMenuItem extends Model
{
    protected $fillable = ['module_id', 'key', 'label', 'route_name', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function url(): ?string
    {
        return Route::has($this->route_name) ? route($this->route_name) : null;
    }

    public function isActive(): bool
    {
        return request()->routeIs($this->route_name);
    }
}
