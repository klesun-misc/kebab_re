<?php

namespace App\Orm;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $spice_left
 * @property int $rounds_won
 * @property int $spice_stolen
 * @property int $player_id
 */
class Competitor extends Model
{
    protected $guarded = [];
}
