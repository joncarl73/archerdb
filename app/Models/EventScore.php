<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventScore extends Model
{
    protected $fillable = [
        'event_id',
        'event_line_time_id',
        'event_participant_id',
        'arrows_per_end',
        'ends_planned',
        'scoring_system',
        'scoring_values',
        'x_value',
        'max_score',
        'total_score',
        'x_count',
    ];

    protected $casts = [
        'scoring_values' => 'array',
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
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function ends(): HasMany
    {
        return $this->hasMany(EventScoreEnd::class);
    }

    /**
     * Recalculate denormalized totals from all ends.
     */
    public function recalcTotals(): void
    {
        $this->loadMissing('ends');

        $total = 0;
        $xCount = 0;

        foreach ($this->ends as $end) {
            $total += (int) ($end->end_score ?? 0);
            $xCount += (int) ($end->x_count ?? 0);
        }

        $this->forceFill([
            'total_score' => $total,
            'x_count' => $xCount,
        ])->save();
    }
}
