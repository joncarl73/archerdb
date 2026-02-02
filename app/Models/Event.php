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

    protected $fillable = [
        'company_id',
        'public_uuid',
        'title',
        'location',
        'kind',
        'starts_on',
        'ends_on',
        'is_published',

        'type',
        'scoring_mode',

        // Rules
        'ruleset_id',

        // Venue layout (Range-owned)
        'range_id',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_published' => 'bool',
        'kind' => EventKind::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->public_uuid) {
                $model->public_uuid = (string) Str::uuid();
            }

            $model->type ??= self::TYPE_OPEN;
            $model->scoring_mode ??= self::SCORING_PERSONAL;
        });

        static::saving(function ($model) {
            if (! in_array($model->type, [self::TYPE_OPEN, self::TYPE_CLOSED], true)) {
                $model->type = self::TYPE_OPEN;
            }

            if (! in_array($model->scoring_mode, [self::SCORING_PERSONAL, self::SCORING_TABLET], true)) {
                $model->scoring_mode = self::SCORING_PERSONAL;
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

    public function range()
    {
        return $this->belongsTo(Range::class);
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
     * Returns the maximum number of slot labels on any lane in the selected range.
     * (UI hint only; ranges can have variable group sizes.)
     */
    public function maxSlotsPerLane(): int
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if (! $range) {
            return 1;
        }

        $groups = $range->laneSlotGroups();
        $max = 1;

        foreach ($groups as $g) {
            $max = max($max, is_array($g) ? count($g) : 1);
        }

        return max(1, $max);
    }

    /**
     * Range-first suggested capacity for a line time.
     * If no range is selected, returns 1 (so existing events won't hard-crash).
     */
    public function suggestedCapacity(): int
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if (! $range) {
            return 1;
        }

        return max(1, (int) $range->positionsCount());
    }

    /**
     * Range-first lane options for CLS assignment (lane_number + slot label).
     * Returns array like: ["1A","1C","2B","2D", ...]
     */
    public function laneOptions(): array
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if (! $range) {
            return ['1SINGLE'];
        }

        return $range->laneOptions();
    }

    // ---------------- Related data ----------------

    public function participants()
    {
        return $this->hasMany(\App\Models\EventParticipant::class);
    }

    public function participantImports()
    {
        return $this->hasMany(\App\Models\ParticipantImport::class);
    }

    public function checkins()
    {
        return $this->hasMany(\App\Models\EventCheckin::class);
    }

    public function scores()
    {
        return $this->hasMany(EventScore::class);
    }
}
