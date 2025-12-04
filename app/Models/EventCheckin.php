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
        'participant_name',
        'participant_email',
        'first_name',
        'last_name',
        'email',
        'user_id',
        'lane_number',
        'lane_slot',
        'checked_in_at',
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
        // 1) Snapshot name on the checkin row (preferred)
        $snap = trim((string) ($this->participant_name ?? ''));
        if ($snap !== '') {
            return $snap;
        }

        // 2) Free-form first/last on this row
        $fn = trim((string) ($this->first_name ?? ''));
        $ln = trim((string) ($this->last_name ?? ''));
        if ($fn !== '' || $ln !== '') {
            return trim("$fn $ln");
        }

        // 3) Roster (EventParticipant) – uses the `name` accessor
        if ($this->relationLoaded('participant') && $this->participant) {
            return $this->participant->name;
        }
        if ($this->participant) {
            return $this->participant->name; // lazy load if needed
        }

        // 4) Fallback: email or participant id
        return $this->email ?: ('#'.$this->participant_id);
    }
}
