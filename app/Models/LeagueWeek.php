<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueWeek extends Model
{
    protected $fillable = ['league_id', 'week_number', 'date', 'is_canceled', 'ends', 'arrows_per_end'];

    protected $casts = ['date' => 'date', 'is_canceled' => 'bool', 'ends' => 'int', 'arrows_per_end' => 'int'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function checkins()
    {
        return $this->hasMany(\App\Models\LeagueCheckin::class, 'week_id');
    }
}
