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
        'kind',              // EventKind backed enum
        'starts_on',
        'ends_on',
        'is_published',

        // Parity with leagues:
        'type',              // 'open' | 'closed'
        'scoring_mode',      // 'personal_device' | 'tablet'

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

            // NOTE: lanes are now Ruleset-owned; do NOT set lane_* here
        });

        static::saving(function ($model) {
            // Type
            if (! in_array($model->type, [self::TYPE_OPEN, self::TYPE_CLOSED], true)) {
                $model->type = self::TYPE_OPEN;
            }

            // Scoring mode
            if (! in_array($model->scoring_mode, [self::SCORING_PERSONAL, self::SCORING_TABLET], true)) {
                $model->scoring_mode = self::SCORING_PERSONAL;
            }

            // NOTE: no lane_* normalization here anymore
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
     * Number of shooters per lane based on the linked ruleset's lane_breakdown.
     * Falls back to 1 if no ruleset or unknown value.
     */
    public function slotsPerLane(): int
    {
        $breakdown = $this->ruleset?->lane_breakdown ?: 'single';

        return $breakdown === 'single' ? 1 : mb_strlen($breakdown);
    }

    /**
     * Suggested capacity for a line time = lanes_count (from ruleset) × slotsPerLane()
     * Note: the ruleset column name you introduced appears as "lanes_count" in your page;
     * keep it consistent with your Ruleset model/migration.
     */
    public function suggestedCapacity(): int
    {
        $lanes = (int) ($this->ruleset?->lanes_count ?? 1);

        return max(1, $lanes * $this->slotsPerLane());
    }

    // ---------------- Legacy helpers removed ----------------
    // laneSlots(), normalizeLaneBreakdown(), and any lane_* fields on Event
    // have been intentionally removed since lanes now live on Ruleset.
    // --------------------------------------------------------

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
