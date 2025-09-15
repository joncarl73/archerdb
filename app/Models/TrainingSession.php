<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'loadout_id',
        'title',
        'session_at',
        'location',
        'distance_m',
        'round_type',
        'arrows_per_end',
        'max_score',
        'x_value',
        'ends_planned',
        'ends_completed',
        'total_score',
        'x_count',
        'duration_minutes',
        'rpe',
        'tags',
        'weather',
        'notes',
    ];

    protected $casts = [
        'session_at'       => 'datetime',
        'tags'             => 'array',
        'weather'          => 'array',
        'distance_m'       => 'int',
        'arrows_per_end'   => 'int',
        'max_score'        => 'int',
        'ends_planned'     => 'int',
        'ends_completed'   => 'int',
        'total_score'      => 'int',
        'x_count'          => 'int',
        'duration_minutes' => 'int',
        'rpe'              => 'int',
        'x_value'          => 'int',
    ];

    /* Relationships ------------------------------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loadout(): BelongsTo
    {
        return $this->belongsTo(Loadout::class);
    }

    public function ends(): HasMany
    {
        return $this->hasMany(TrainingEnd::class)->orderBy('end_number');
    }

    /* Scopes ------------------------------------------------------------- */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('session_at')->orderByDesc('id');
    }

    /* Helpers ------------------------------------------------------------ */

    /**
     * Ensure exactly $count ends exist (1..$count).
     * Missing ends are created with the correct number of null scores.
     */
    public function ensureEnds(int $count): void
    {
        $count    = max(0, $count);
        $existing = $this->ends()->pluck('id', 'end_number'); // [end_number => id]
        $len      = max(1, (int) ($this->arrows_per_end ?? 3));

        // Create missing
        for ($i = 1; $i <= $count; $i++) {
            if (! isset($existing[$i])) {
                $this->ends()->create([
                    'end_number' => $i,
                    'scores'     => array_fill(0, $len, null),
                    'end_score'  => 0,
                    'x_count'    => 0,
                ]);
            }
        }

        // Remove extras (optional)
        if ($existing->isNotEmpty()) {
            $this->ends()
                ->where('end_number', '>', $count)
                ->delete();
        }

        $this->refreshTotals();
    }

    public function xCountAs(): int
    {
        return $this->x_value ?? 10;
    }

    /**
     * Recalculate and persist session aggregates from ends.
     * - total_score = sum(end_score)
     * - x_count     = sum(x_count)
     * - ends_completed = # of ends with NO nulls (fully scored)
     */
    public function refreshTotals(): void
    {
        $ends = $this->ends()->get(['scores', 'end_score', 'x_count']);

        $total  = (int) $ends->sum('end_score');
        $xTotal = (int) $ends->sum('x_count');

        $completed = 0;
        foreach ($ends as $e) {
            $arr = (array) ($e->scores ?? []);
            if ($arr !== [] && !in_array(null, $arr, true)) {
                $completed++;
            }
        }

        $this->forceFill([
            'total_score'    => $total,
            'x_count'        => $xTotal,
            'ends_completed' => $completed,
        ])->saveQuietly();
    }

    /** Maximum theoretical points per end (useful for UI) */
    public function maxPointsPerEnd(): int
    {
        return (int) ($this->arrows_per_end ?? 3) * (int) ($this->max_score ?? 10);
    }
}