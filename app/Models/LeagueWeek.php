<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueWeek extends Model
{
    protected $fillable = ['league_id', 'week_number', 'date', 'is_canceled'];

    protected $casts = ['date' => 'date', 'is_canceled' => 'bool'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
