<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLineTime extends Model
{
    protected $fillable = [
        'event_id', 'line_date', 'start_time', 'end_time', 'capacity', 'notes',
    ];

    protected $casts = [
        'line_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Helpers for formatting
    public function getStartsAtDisplayAttribute(): string
    {
        $d = $this->line_date?->format('Y-m-d') ?? '';
        $t = $this->start_time; // 'HH:MM:SS'

        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "$d $t")->format('m/d/Y g:ia');
    }

    public function getEndsAtDisplayAttribute(): string
    {
        $d = $this->line_date?->format('Y-m-d') ?? '';
        $t = $this->end_time; // 'HH:MM:SS'

        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', "$d $t")->format('m/d/Y g:ia');
    }

    public function getRangeDisplayAttribute(): string
    {
        return "{$this->starts_at_display} – {$this->ends_at_display}";
    }

    public function checkins()
    {
        return $this->hasMany(\App\Models\EventCheckin::class, 'event_line_time_id');
    }

    public function scores()
    {
        return $this->hasMany(EventScore::class, 'event_line_time_id');
    }
}
