<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model
{
    protected $table = 'event_participants';

    protected $fillable = [
        'event_id', 'user_id', 'notes', 'first_name', 'last_name', 'email', 'division_name', 'bow_type', 'is_para', 'uses_wheelchair', 'line_time_id', 'assigned_lane', 'assigned_slot', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_para' => 'boolean',
        'uses_wheelchair' => 'boolean',
    ];

    // Expose a virtual "name" attribute for blades/controllers
    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
