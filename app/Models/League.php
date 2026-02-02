<?php

namespace App\Models;

use App\Enums\LaneBreakdown;
use App\Enums\LeagueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class League extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_uuid',
        'owner_id',
        'company_id',
        'title',
        'location',
        'length_weeks',
        'day_of_week',
        'start_date',
        'type',
        'is_published',
        'is_archived',
        'price_cents',
        'currency',
        'stripe_account_id',
        'stripe_product_id',
        'stripe_price_id',

        // Legacy lane config (still supported for older leagues)
        'lanes_count',
        'lane_breakdown',

        // Scoring config
        'ends_per_day',
        'arrows_per_end',
        'x_ring_value',
        'scoring_mode',

        'registration_start_date',
        'registration_end_date',

        // Range (new preferred source for lanes/capacity/lane layout)
        'range_id',
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

        // range_id cast is not strictly necessary, but nice to have
        'range_id' => 'int',
    ];

    protected static function booted(): void
    {
        static::creating(function (League $league) {
            if (empty($league->public_uuid)) {
                $league->public_uuid = (string) Str::uuid();
            }
        });
    }

    // ---------------- Relationships ----------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(LeagueWeek::class)->orderBy('week_number');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueParticipant::class)->orderBy('last_name');
    }

    public function info()
    {
        return $this->hasOne(\App\Models\LeagueInfo::class);
    }

    public function collaborators()
    {
        return $this->belongsToMany(\App\Models\User::class, 'league_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function range()
    {
        return $this->belongsTo(Range::class);
    }

    // ---------------- Convenience ----------------

    /**
     * Normalized lane_breakdown string value whether casted enum or raw string.
     */
    public function getLaneBreakdownValueAttribute(): string
    {
        return $this->lane_breakdown instanceof LaneBreakdown
            ? $this->lane_breakdown->value
            : (string) $this->lane_breakdown;
    }

    /**
     * Range-first: maximum number of positions (slot labels) on any lane for UI.
     * Legacy fallback uses lane_breakdown ('AB' => 2, 'ABCD' => 4, 'single' => 1).
     *
     * Note: with a Range, slots-per-lane may vary, so we return the MAX for safe UI sizing.
     */
    public function positionsPerLane(): int
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if ($range) {
            // Prefer Range helper if present
            if (method_exists($range, 'laneSlotGroups')) {
                $groups = $range->laneSlotGroups();
            } else {
                $groups = is_array($range->lane_slot_groups) ? $range->lane_slot_groups : [];
            }

            $max = 1;
            foreach ($groups as $g) {
                if (is_array($g)) {
                    $max = max($max, count($g));
                }
            }

            return max(1, $max);
        }

        // Legacy fallback
        $raw = $this->getLaneBreakdownValueAttribute();

        return match ($raw) {
            LaneBreakdown::AB->value => 2,
            LaneBreakdown::ABCD->value => 4,
            default => 1,
        };
    }

    /**
     * Range-first total shooting positions for the whole league.
     * - If range selected: use range.positionsCount()
     * - Else legacy: lanes_count × positionsPerLane()
     */
    public function totalPositions(): int
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if ($range) {
            if (method_exists($range, 'positionsCount')) {
                return max(1, (int) $range->positionsCount());
            }

            // Safe inline compute if Range model doesn't yet have positionsCount()
            $groups = is_array($range->lane_slot_groups) ? $range->lane_slot_groups : [];
            $perBale = 0;

            foreach ($groups as $g) {
                $perBale += is_array($g) ? count($g) : 0;
            }

            $bales = (int) ($range->bales_count ?? 1);

            return max(1, $bales * max(1, $perBale));
        }

        return max(1, (int) $this->lanes_count) * $this->positionsPerLane();
    }

    /**
     * Suggested capacity for signups.
     * Today your leagues generally use lanes_count × breakdown; now Range should drive it.
     *
     * This is intentionally an alias of totalPositions() so your UI can use a common term.
     */
    public function suggestedCapacity(): int
    {
        return $this->totalPositions();
    }

    /**
     * Range-first lane options for CLS assignment (lane_number + slot label).
     * If no range selected, returns legacy options derived from lanes_count + lane_breakdown.
     *
     * Examples:
     * - Range: ["1A","1C","2B","2D", ...]
     * - Legacy ABCD: ["1A","1B","1C","1D","2A",...]
     */
    public function laneOptions(): array
    {
        $range = $this->relationLoaded('range') ? $this->range : null;

        if (! $range && $this->range_id) {
            $range = $this->range()->first();
        }

        if ($range) {
            if (method_exists($range, 'laneOptions')) {
                return $range->laneOptions();
            }

            // Safe fallback if Range model doesn't yet have laneOptions()
            $bales = max(1, (int) ($range->bales_count ?? 1));
            $lanesPerBale = max(1, (int) ($range->lanes_per_bale ?? 1));

            $groups = is_array($range->lane_slot_groups) ? $range->lane_slot_groups : [];
            $fallback = [
                ['A', 'C'],
                ['B', 'D'],
            ];

            // Normalize group count to lanes_per_bale
            if (count($groups) < $lanesPerBale) {
                $groups = array_pad($groups, $lanesPerBale, []);
            } elseif (count($groups) > $lanesPerBale) {
                $groups = array_slice($groups, 0, $lanesPerBale);
            }

            // If empty/malformed, use fallback
            $allEmpty = true;
            foreach ($groups as $g) {
                if (is_array($g) && count($g) > 0) {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                $groups = $fallback;
            }

            $lanesCount = $bales * $lanesPerBale;
            $opts = [];

            for ($laneNumber = 1; $laneNumber <= $lanesCount; $laneNumber++) {
                $laneWithinBale = ($laneNumber - 1) % $lanesPerBale; // 0..lanesPerBale-1
                $slots = $groups[$laneWithinBale] ?? ['SINGLE'];

                foreach ($slots as $slot) {
                    $slot = strtoupper(trim((string) $slot));
                    if ($slot === '') {
                        continue;
                    }
                    $opts[] = (string) $laneNumber.$slot;
                }
            }

            return $opts ?: ['1SINGLE'];
        }

        // Legacy behavior:
        $lanes = max(1, (int) $this->lanes_count);
        $raw = $this->getLaneBreakdownValueAttribute();

        $letters = match ($raw) {
            LaneBreakdown::ABCD->value => ['A', 'B', 'C', 'D'],
            LaneBreakdown::AB->value => ['A', 'B'],
            default => ['single'],
        };

        $opts = [];
        for ($i = 1; $i <= $lanes; $i++) {
            foreach ($letters as $s) {
                $opts[] = (string) $i.($s === 'single' ? 'single' : $s);
            }
        }

        return $opts;
    }
}
