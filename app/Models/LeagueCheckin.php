<?php

// app/Models/LeagueCheckin.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeagueCheckin extends Model
{
    protected $fillable = [
        'league_id', 'participant_id', 'participant_name', 'participant_email',
        'week_number', 'lane_number', 'lane_slot', 'checked_in_at',
    ];
}
