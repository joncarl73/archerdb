<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventScoreEnd extends Model
{
    protected $fillable = [
        'event_score_id',
        'end_number',
        'scores',
        'end_score',
        'x_count',
    ];

    protected $casts = [
        'scores' => 'array',
    ];

    public function score(): BelongsTo
    {
        return $this->belongsTo(EventScore::class, 'event_score_id');
    }

    /**
     * Convenience helper: update the arrow list and derived fields.
     *
     * @param  array<int,int|null>  $scores
     */
    public function fillScoresAndSave(array $scores): void
    {
        $parent = $this->score;

        $sum = 0;
        $xCount = 0;

        $xVal = (int) ($parent?->x_value ?? 0);

        foreach ($scores as $v) {
            if ($v === null) {
                continue;
            }

            $val = (int) $v;
            $sum += $val;

            if ($xVal && $val === $xVal) {
                $xCount++;
            }
        }

        $this->forceFill([
            'scores' => $scores,
            'end_score' => $sum,
            'x_count' => $xCount,
        ])->save();

        $parent?->recalcTotals();
    }
}
