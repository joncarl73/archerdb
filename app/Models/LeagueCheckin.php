<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueCheckin extends Model
{
    protected $fillable = [
        'league_id',
        'participant_id',
        'participant_name',
        'participant_email',
        'week_number',
        'lane_number',
        'lane_slot',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    // ← NEW: relations
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function participant(): BelongsTo
    {
        // foreign key column is participant_id on this table
        return $this->belongsTo(LeagueParticipant::class, 'participant_id');
    }
}
