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

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    /** Filter weeks by available context (event first, else league). */
    public function scopeForContext($q, ?\App\Models\Event $event, ?\App\Models\League $league)
    {
        if ($event && \Illuminate\Support\Facades\Schema::hasColumn('league_weeks', 'event_id')) {
            return $q->where('event_id', $event->id);
        }
        if ($league) {
            return $q->where('league_id', $league->id);
        }

        return $q->whereRaw('1=0'); // no context
    }

    /** "Session" label for non-league events; "Week" for leagues; or custom label. */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        // If linked to a league, keep legacy "Week #"
        if ($this->league_id) {
            return 'Week '.$this->week_number;
        }

        // Otherwise, generic
        return 'Session '.$this->week_number;
    }
}
