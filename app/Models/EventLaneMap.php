<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLaneMap extends Model
{
    protected $fillable = ['event_id', 'line_time_id', 'lane_number', 'slot', 'capacity'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lineTime()
    {
        return $this->belongsTo(EventLineTime::class, 'line_time_id');
    }
}
