<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Convenience
    public function getDisplayNameAttribute(): string
    {
        $fn = trim((string) ($this->first_name ?? ''));
        $ln = trim((string) ($this->last_name ?? ''));
        $name = trim("$fn $ln");

        return $name !== '' ? $name : ($this->email ?: '#'.$this->id);
    }
}
