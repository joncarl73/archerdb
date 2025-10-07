<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDivision extends Model
{
    protected $fillable = ['event_id', 'name', 'rules', 'capacity'];

    protected $casts = ['rules' => 'array'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function participants()
    {
        return $this->hasMany(LeagueParticipant::class, 'event_division_id');
    }
}
