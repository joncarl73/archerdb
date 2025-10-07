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
        'total_score', 'x_count', 'event_id',
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

    // app/Models/LeagueWeekScore.php
    public function toLiveRow(): array
    {
        $name = trim(($this->participant->first_name ?? '').' '.($this->participant->last_name ?? ''));
        // If you already compute 10s/9s elsewhere, keep that logic.
        // Otherwise derive them from ends:
        $tens = 0;
        $nines = 0;
        foreach ($this->ends as $end) {
            foreach ((array) $end->scores as $v) {
                if ($v === null) {
                    continue;
                }
                if ((int) $v === 10 && (int) $this->x_value !== 10) {
                    $tens++;
                }
                if ((int) $v === 9) {
                    $nines++;
                }
            }
        }

        // Lane/slot from check-in (optional)
        $checkin = \App\Models\LeagueCheckin::where('league_id', $this->league_id)
            ->where('week_number', $this->week->week_number)
            ->where('participant_id', $this->league_participant_id)
            ->first();

        return [
            'id' => $this->id,
            'name' => $name,
            'lane' => $checkin?->lane_number,
            'slot' => $checkin?->lane_slot,
            'x' => (int) $this->x_count,
            'tens' => (int) $tens,
            'nines' => (int) $nines,
            'score' => (int) $this->total_score,
        ];
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }
}
