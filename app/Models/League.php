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
        'public_uuid', 'owner_id', 'title', 'location', 'length_weeks', 'day_of_week', 'start_date', 'type', 'is_published', 'is_archived', 'price_cents', 'currency', 'stripe_account_id', 'stripe_product_id', 'stripe_price_id', 'lanes_count', 'lane_breakdown',
    ];

    protected $casts = [
        'start_date' => 'date',
        'type' => LeagueType::class,
        'lane_breakdown' => LaneBreakdown::class,
        'is_published' => 'bool',
        'is_archived' => 'bool',
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
}
