<?php

namespace App\Models;

use App\Enums\EventKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    // ---- Scoring modes (aligned with leagues)
    public const SCORING_PERSONAL = 'personal_device';

    public const SCORING_TABLET = 'tablet';

    // ---- Registration/payment type (aligned with leagues/products)
    public const TYPE_OPEN = 'open';

    public const TYPE_CLOSED = 'closed';

    // ---- Lane breakdown options (aligned with leagues)
    // 'single' = 1 per lane, 'AB' = 2, 'ABCD' = 4, 'ABCDEF' = 6
    public const LANE_BREAKDOWN_SINGLE = 'single';

    public const LANE_BREAKDOWN_OPTS = ['single', 'AB', 'ABCD', 'ABCDEF'];

    protected $fillable = [
        'company_id',
        'public_uuid',
        'title',
        'location',
        'kind',              // EventKind backed enum
        'starts_on',
        'ends_on',
        'is_published',

        // Parity with leagues:
        'type',              // 'open' | 'closed'
        'scoring_mode',      // 'personal_device' | 'tablet'
        'lanes_count',       // int

        // Rules
        'ruleset_id',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_published' => 'bool',
        'kind' => EventKind::class, // backed enum cast (string)
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->public_uuid) {
                $model->public_uuid = (string) Str::uuid();
            }

            // Sensible defaults to mirror league creation UX
            $model->type ??= self::TYPE_OPEN;
            $model->scoring_mode ??= self::SCORING_PERSONAL;
            $model->lane_breakdown ??= self::LANE_BREAKDOWN_SINGLE;
        });

        // Normalize persisted values for safety (in case form validation misses)
        static::saving(function ($model) {
            // Type
            if (! in_array($model->type, [self::TYPE_OPEN, self::TYPE_CLOSED], true)) {
                $model->type = self::TYPE_OPEN;
            }

            // Scoring mode
            if (! in_array($model->scoring_mode, [self::SCORING_PERSONAL, self::SCORING_TABLET], true)) {
                $model->scoring_mode = self::SCORING_PERSONAL;
            }

            // Lane breakdown
            $model->lane_breakdown = $model->normalizeLaneBreakdown($model->lane_breakdown);

            // lanes_count guard
            if (! is_numeric($model->lanes_count) || (int) $model->lanes_count < 1) {
                $model->lanes_count = 1;
            }
        });
    }

    // ---------------- Relationships ----------------

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function ruleset()
    {
        return $this->belongsTo(Ruleset::class);
    }

    public function rulesetOverrides()
    {
        return $this->hasOne(EventRulesetOverride::class);
    }

    public function lineTimes()
    {
        return $this->hasMany(EventLineTime::class)
            ->orderBy('line_date')
            ->orderBy('start_time');
    }

    public function collaborators()
    {
        // Matches league_users style but for events
        return $this->belongsToMany(User::class, 'event_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners()
    {
        return $this->collaborators()->wherePivot('role', 'owner');
    }

    public function managers()
    {
        return $this->collaborators()->wherePivot('role', 'manager');
    }

    // ---------------- Convenience ----------------

    public function userRoleFor(User $user): ?string
    {
        return $this->collaborators()
            ->where('user_id', $user->id)
            ->first()?->pivot?->role;
    }

    public function belongsToSameCompany(User $user): bool
    {
        return (int) $this->company_id === (int) $user->company_id;
    }

    public function isSingleDay(): bool
    {
        return $this->kind === EventKind::SingleDay;
    }

    public function isOpen(): bool
    {
        return $this->type === self::TYPE_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->type === self::TYPE_CLOSED;
    }

    /**
     * Return the lane slots used for assignment:
     *  - 'single' => ['single']
     *  - 'AB'     => ['A','B']
     *  - 'ABCD'   => ['A','B','C','D']
     *  - 'ABCDEF' => ['A','B','C','D','E','F']
     */
    public function laneSlots(): array
    {
        if ($this->lane_breakdown === self::LANE_BREAKDOWN_SINGLE) {
            return ['single'];
        }

        return preg_split('//u', $this->lane_breakdown, -1, PREG_SPLIT_NO_EMPTY) ?: ['single'];
    }

    /**
     * Number of shooters per lane based on lane_breakdown.
     */
    public function slotsPerLane(): int
    {
        $breakdown = $this->ruleset?->lane_breakdown ?: 'single';

        return $breakdown === 'single' ? 1 : mb_strlen($breakdown);
    }

    public function suggestedCapacity(): int
    {
        return (int) $this->lanes_count * $this->slotsPerLane();
    }

    // ---------------- Internals ----------------

    /**
     * Normalize lane_breakdown input into one of the allowed options.
     */
    protected function normalizeLaneBreakdown(?string $value): string
    {
        $v = trim((string) $value);

        // Allow legacy values to pass-through cleanly
        if ($v === 'double') {
            return 'AB'; // migrate old enum('single','double') -> 'AB'
        }

        // Accept common aliases
        $aliases = [
            'single' => 'single',
            'ab' => 'AB',
            'a/b' => 'AB',
            'abcd' => 'ABCD',
            'a/b/c/d' => 'ABCD',
            'abcdef' => 'ABCDEF',
            'a/b/c/d/e/f' => 'ABCDEF',
        ];

        $vLower = strtolower($v);
        if (isset($aliases[$vLower])) {
            return $aliases[$vLower];
        }

        // Already a valid value?
        if (in_array($v, self::LANE_BREAKDOWN_OPTS, true)) {
            return $v;
        }

        // Fallback
        return self::LANE_BREAKDOWN_SINGLE;
    }

    public function participants()
    {
        return $this->hasMany(\App\Models\EventParticipant::class);
    }

    public function checkins()
    {
        return $this->hasMany(\App\Models\EventCheckin::class);
    }
}
