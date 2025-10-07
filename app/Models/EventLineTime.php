<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLineTime extends Model
{
    protected $fillable = ['event_id', 'label', 'starts_at', 'ends_at', 'capacity'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lanes()
    {
        return $this->hasMany(EventLaneMap::class, 'line_time_id');
    }

    public function participants()
    {
        return $this->hasMany(LeagueParticipant::class, 'assigned_line_time_id');
    }
}
