<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueParticipant extends Model
{
    protected $fillable = ['league_id', 'user_id', 'first_name', 'last_name', 'email', 'checked_in'];

    protected $casts = ['checked_in' => 'bool'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
