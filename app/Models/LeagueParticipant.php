<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueParticipant extends Model
{
    protected $fillable = [
        'event_id', 'league_id', 'user_id', 'first_name', 'last_name', 'email',
        'event_division_id', 'preferred_line_time_id', 'assigned_line_time_id',
        'assigned_lane_number', 'assigned_lane_slot', 'assignment_status', 'checked_in',
    ];

    protected $casts = ['checked_in' => 'bool'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function division()
    {
        return $this->belongsTo(\App\Models\EventDivision::class, 'event_division_id');
    }

    public function preferredLineTime()
    {
        return $this->belongsTo(\App\Models\EventLineTime::class, 'preferred_line_time_id');
    }

    public function assignedLineTime()
    {
        return $this->belongsTo(\App\Models\EventLineTime::class, 'assigned_line_time_id');
    }
}
