<?php

namespace App\Models;

use App\Enums\LaneBreakdown;
use App\Enums\LeagueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class League extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_uuid', 'owner_id', 'company_id', 'title', 'location', 'length_weeks', 'day_of_week', 'start_date', 'type', 'is_published', 'is_archived', 'price_cents', 'currency', 'stripe_account_id', 'stripe_product_id', 'stripe_price_id', 'lanes_count', 'lane_breakdown', 'ends_per_day', 'arrows_per_end', 'x_ring_value', 'scoring_mode', 'registration_start_date', 'registration_end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'type' => LeagueType::class,
        'lane_breakdown' => LaneBreakdown::class,
        'is_published' => 'bool',
        'is_archived' => 'bool',
        'ends_per_day' => 'int',
        'arrows_per_end' => 'int',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (League $league) {
            if (empty($league->public_uuid)) {
                $league->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(LeagueWeek::class)->orderBy('week_number');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueParticipant::class)->orderBy('last_name');
    }

    // app/Models/League.php
    public function positionsPerLane(): int
    {
        $mode = $this->lane_breakdown instanceof LaneBreakdown
            ? $this->lane_breakdown->value
            : (string) $this->lane_breakdown;

        return match ($mode) {
            'single' => 1,
            'ab' => 2,
            'abcd' => 4,
            default => 1,
        };
    }

    public function totalPositions(): int
    {
        return max(0, (int) $this->lanes_count) * $this->positionsPerLane();
    }

    // app/Models/League.php
    // use App\Enums\LaneBreakdown;

    public function getLaneBreakdownValueAttribute(): string
    {
        return $this->lane_breakdown instanceof LaneBreakdown
            ? $this->lane_breakdown->value
            : (string) $this->lane_breakdown;
    }

    // app/Models/League.php

    public function laneOptions(): array
    {
        // supports enum or string stored value
        $breakdown = (string) ($this->lane_breakdown?->value ?? $this->lane_breakdown);

        $suffixes = match ($breakdown) {
            'single' => [''],
            'ab' => ['A', 'B'],
            'abcd' => ['A', 'B', 'C', 'D'],
            default => [''],
        };

        $out = [];
        for ($i = 1; $i <= (int) $this->lanes_count; $i++) {
            foreach ($suffixes as $s) {
                $code = $s === '' ? (string) $i : $i.$s;     // "1", "1A", "1B", ...
                $out[$code] = 'Lane '.$code;                 // label
            }
        }

        return $out; // assoc: ['1' => 'Lane 1', '1A' => 'Lane 1A', ...]
    }

    public function info()
    {
        return $this->hasOne(\App\Models\LeagueInfo::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function collaborators()
    {
        return $this->belongsToMany(\App\Models\User::class, 'league_users')
            ->withPivot('role')
            ->withTimestamps();
    }
}
