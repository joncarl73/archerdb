<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCheckin extends Model
{
    protected $fillable = [
        'event_id',
        'event_line_time_id',
        'participant_id',
        'first_name',
        'last_name',
        'email',
        'user_id',
        'lane_number',
        'lane_slot',
    ];

    protected $casts = [
        'lane_number' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function lineTime(): BelongsTo
    {
        return $this->belongsTo(EventLineTime::class, 'event_line_time_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'participant_id');
    }

    public function getDisplayNameAttribute(): string
    {
        // 1) free-form
        $fn = trim((string) ($this->first_name ?? ''));
        $ln = trim((string) ($this->last_name ?? ''));
        if ($fn !== '' || $ln !== '') {
            return trim("$fn $ln");
        }

        // 2) roster
        if ($this->relationLoaded('participant') && $this->participant) {
            return $this->participant->display_name;
        }
        if ($this->participant) {
            return $this->participant->display_name; // resolves lazily
        }

        // 3) fallback
        return $this->email ?: '#'.$this->participant_id;
    }
}
