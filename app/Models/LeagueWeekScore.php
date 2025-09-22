<?php

// app/Models/LeagueWeekScore.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueWeekScore extends Model
{
    protected $fillable = [
        'league_id', 'league_week_id', 'league_participant_id',
        'arrows_per_end', 'ends_planned', 'max_score', 'x_value',
        'total_score', 'x_count',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(LeagueWeek::class, 'league_week_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(LeagueParticipant::class, 'league_participant_id');
    }

    public function ends(): HasMany
    {
        return $this->hasMany(LeagueWeekEnd::class);
    }

    public function recalcTotals(): void
    {
        $this->loadMissing('ends');
        $ts = 0;
        $xs = 0;
        foreach ($this->ends as $e) {
            $ts += (int) ($e->end_score ?? 0);
            $xs += (int) ($e->x_count ?? 0);
        }
        $this->forceFill(['total_score' => $ts, 'x_count' => $xs])->save();
    }
}
