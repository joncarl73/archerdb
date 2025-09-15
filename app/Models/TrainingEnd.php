<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEnd extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'end_number',
        'scores',
        'end_score',
        'x_count',
    ];

    protected $casts = [
        'scores'    => 'array',
        'end_score' => 'int',
        'x_count'   => 'int',
    ];

    // Touch parent updated_at whenever an end changes
    protected $touches = ['trainingSession'];

    /* Relationship ------------------------------------------------------- */

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    /* Events to keep totals & per-end summaries in sync ------------------ */

    protected static function booted(): void
    {
        // Normalize scores and recalc this end before save
        static::saving(function (self $end) {
            $end->loadMissing('trainingSession');

            $session    = $end->trainingSession;
            $len        = max(1, (int) ($session->arrows_per_end ?? 3));
            $maxScore   = max(1, (int) ($session->max_score ?? 10));
            $xValue     = (int) ($session->x_value ?? 10);
            $maxAllowed = max($maxScore, $xValue); // allow X=11

            $scores = is_array($end->scores) ? array_values($end->scores) : [];

            // Trim/pad to the expected length
            $scores = array_slice($scores, 0, $len);
            if (count($scores) < $len) {
                $scores = array_pad($scores, $len, null);
            }

            // Recalculate end_score and x_count based on normalized input
            [$scores, $endScore, $xCount] = self::recalculate($scores, $maxAllowed, $xValue);

            $end->scores    = $scores;
            $end->end_score = $endScore;
            $end->x_count   = $xCount;
        });

        // After save/delete, update the parent aggregates
        static::saved(function (self $end) {
            $end->trainingSession?->refreshTotals();
        });

        static::deleted(function (self $end) {
            $end->trainingSession?->refreshTotals();
        });
    }

    /**
     * Convenience for callers that previously used $end->recalcTotals(...).
     * Recomputes from current scores (or provided raw scores) and persists.
     */
    public function recalcTotals(?int $maxScore = null, ?array $rawScores = null): void
    {
        $this->loadMissing('trainingSession');

        $len        = max(1, (int) ($this->trainingSession->arrows_per_end ?? 3));
        $max        = $maxScore ?? max(1, (int) ($this->trainingSession->max_score ?? 10));
        $xValue     = (int) ($this->trainingSession->x_value ?? 10);
        $maxAllowed = max($max, $xValue);

        $scoresIn = $rawScores ?? (is_array($this->scores) ? array_values($this->scores) : []);

        // Trim/pad
        $scoresIn = array_slice($scoresIn, 0, $len);
        if (count($scoresIn) < $len) {
            $scoresIn = array_pad($scoresIn, $len, null);
        }

        [$scores, $endScore, $xCount] = self::recalculate($scoresIn, $maxAllowed, $xValue);

        $this->scores    = $scores;
        $this->end_score = $endScore;
        $this->x_count   = $xCount;
        $this->save();
    }

    /**
     * Accept keypad entries and save (triggers saving() to normalize & recalc).
     *
     * @param array<int, mixed> $rawScores values like 11, 10, 9, 'X', 'M', null…
     */
    public function fillScoresAndSave(array $rawScores): void
    {
        $this->scores = $rawScores;
        $this->save();
    }

    /**
     * Normalize a single keypad value into:
     *  - null (no shot yet)
     *  - 0..$maxAllowed (M -> 0, numeric clamped), with X recognized via $xValue
     * Also flags whether it was an "X".
     */
    protected static function normalizeOne(mixed $raw, int $maxAllowed, int $xValue, bool &$isX): ?int
    {
        $isX = false;

        if ($raw === null || $raw === '') {
            return null;
        }

        // String inputs: 'M', 'X', numeric-ish
        if (is_string($raw)) {
            $v = strtoupper(trim($raw));
            if ($v === 'M') {
                return 0; // miss
            }
            if ($v === 'X') {
                $isX = true;
                return $xValue; // score exactly as X value (10 or 11)
            }
            if (is_numeric($v)) {
                $num = (int) $v;
                if ($num === $xValue) {
                    $isX = true;
                }
                return max(0, min($maxAllowed, $num));
            }
            return null;
        }

        // Numeric inputs: clamp to maxAllowed, treat == xValue as X
        if (is_int($raw) || is_float($raw)) {
            $num = (int) $raw;
            if ($num === $xValue) {
                $isX = true;
            }
            if ($num < 0) $num = 0;
            if ($num > $maxAllowed) $num = $maxAllowed;
            return $num;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $scores
     * @return array{0: array<int, ?int>, 1: int, 2: int} [normalizedScores, endScore, xCount]
     */
    protected static function recalculate(array $scores, int $maxAllowed, int $xValue): array
    {
        $endScore = 0;
        $xCount   = 0;
        $out      = [];

        foreach ($scores as $raw) {
            $isX = false;
            $val = self::normalizeOne($raw, $maxAllowed, $xValue, $isX);
            $out[] = $val;

            if ($val !== null) {
                $endScore += $val;
                if ($isX) {
                    $xCount++;
                }
            }
        }

        return [$out, $endScore, $xCount];
    }
}