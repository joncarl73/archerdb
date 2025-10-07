<?php

// app/Models/KioskSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskSession extends Model
{
    protected $fillable = [
        'league_id', 'week_number', 'lanes', 'token', 'is_active', 'created_by', 'expires_at', 'participants', 'event_id', 'event_line_time_id',
    ];

    protected $casts = [
        'lanes' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'participants' => 'array',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function lineTime()
    {
        return $this->belongsTo(\App\Models\EventLineTime::class, 'event_line_time_id');
    }
}
