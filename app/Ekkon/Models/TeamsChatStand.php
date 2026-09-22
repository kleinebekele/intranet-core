<?php

namespace App\Ekkon\Models;

use Illuminate\Database\Eloquent\Model;

/** Bis wann ein Chat gelesen wurde – der Merker des Lauschers (überlebt Neustarts und Deploys). */
class TeamsChatStand extends Model
{
    protected $table = 'ekkon_teams_chat_stand';

    protected $primaryKey = 'chat_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['zuletzt_gesehen_am' => 'datetime'];
    }
}
