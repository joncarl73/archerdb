<?php

// app/Models/LeagueWeekEnd.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueWeekEnd extends Model
{
    protected $fillable = ['league_week_score_id', 'end_number', 'scores', 'end_score', 'x_count'];

    protected $casts = ['scores' => 'array'];

    public function score(): BelongsTo
    {
        return $this->belongsTo(LeagueWeekScore::class, 'league_week_score_id');
    }

    // same helper your TrainingEnd uses
    public function fillScoresAndSave(array $scores): void
    {
        $scores = array_values($scores);
        $sum = 0;
        $x = 0;
        $parent = $this->score()->first();
        $xVal = (int) ($parent?->x_value ?? 10);
        foreach ($scores as $v) {
            if (is_int($v)) {
                $sum += $v;
                if ($v === $xVal) {
                    $x++;
                }
            }
        }
        $this->forceFill([
            'scores' => $scores,
            'end_score' => $sum,
            'x_count' => $x,
        ])->save();

        $parent?->recalcTotals();
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }
}
