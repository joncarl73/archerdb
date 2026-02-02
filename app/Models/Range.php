<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Range extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'environment',       // 'indoor' | 'outdoor'
        'distances',         // comma separated string: "9m,18m"
        'bales_count',
        'targets_per_bale',
        'lanes_per_bale',
        'lane_slot_groups',  // JSON => array-of-arrays, e.g. [["A","C"],["B","D"]]
        'is_active',
        'notes',
    ];

    protected $casts = [
        'lane_slot_groups' => 'array',
        'is_active' => 'boolean',
        'bales_count' => 'integer',
        'targets_per_bale' => 'integer',
        'lanes_per_bale' => 'integer',
    ];

    // ---------------- Relationships ----------------

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ---------------- Core computed values ----------------

    public function lanesCount(): int
    {
        return max(1, (int) $this->bales_count) * max(1, (int) $this->lanes_per_bale);
    }

    /**
     * Returns lane slot groups normalized.
     * Must be an array of arrays, length == lanes_per_bale.
     *
     * Default fallback:
     *  - 4 targets / 2 lanes => [["A","C"],["B","D"]]
     */
    public function laneSlotGroups(): array
    {
        $fallback = [
            ['A', 'C'],
            ['B', 'D'],
        ];

        $lanesPerBale = max(1, (int) $this->lanes_per_bale);
        $groups = is_array($this->lane_slot_groups) ? $this->lane_slot_groups : [];

        // Normalize to array-of-arrays of uppercase trimmed strings
        $groups = array_map(function ($g) {
            $g = is_array($g) ? $g : [];
            $g = array_values(array_filter(
                array_map(fn ($x) => strtoupper(trim((string) $x)), $g),
                fn ($x) => $x !== ''
            ));

            return $g;
        }, $groups);

        // Force group count to match lanes_per_bale (pad/trim)
        if (count($groups) < $lanesPerBale) {
            $groups = array_pad($groups, $lanesPerBale, []);
        } elseif (count($groups) > $lanesPerBale) {
            $groups = array_slice($groups, 0, $lanesPerBale);
        }

        // If all groups are empty (or malformed), use fallback when it matches shape.
        $allEmpty = true;
        foreach ($groups as $g) {
            if (is_array($g) && count($g) > 0) {
                $allEmpty = false;
                break;
            }
        }

        // If lanes_per_bale is 2, fallback is perfect. Otherwise, return at least sane empty groups.
        if ($allEmpty && $lanesPerBale === 2) {
            return $fallback;
        }

        // If groups are empty and lanes_per_bale != 2, create a safe default: one slot per lane.
        if ($allEmpty) {
            $safe = [];
            for ($i = 0; $i < $lanesPerBale; $i++) {
                $safe[] = ['SINGLE'];
            }

            return $safe;
        }

        return $groups;
    }

    /**
     * Total shooting positions across the whole range (lane_number x lane_slot).
     * Example: 10 bales, [["A","C"],["B","D"]] => per bale = 4 => positions = 40
     */
    public function positionsCount(): int
    {
        $perBale = array_sum(array_map(fn ($g) => is_array($g) ? count($g) : 0, $this->laneSlotGroups()));

        return max(1, (int) $this->bales_count) * max(1, $perBale);
    }

    /**
     * Build options consistent with CLS: lane_number + slot label.
     * Returns ["1A","1C","2B",...]
     */
    public function laneOptions(): array
    {
        $options = [];
        $groups = $this->laneSlotGroups();
        $lanesPerBale = max(1, (int) $this->lanes_per_bale);
        $lanesCount = $this->lanesCount();

        for ($laneNumber = 1; $laneNumber <= $lanesCount; $laneNumber++) {
            $laneWithinBale = ($laneNumber - 1) % $lanesPerBale; // 0..lanesPerBale-1
            $slots = $groups[$laneWithinBale] ?? ['SINGLE'];

            foreach ($slots as $slot) {
                $slot = strtoupper(trim((string) $slot));
                if ($slot === '') {
                    continue;
                }
                $options[] = (string) $laneNumber.$slot;
            }
        }

        // Never return empty
        return $options ?: ['1SINGLE'];
    }

    // ---------------- Convenience helpers ----------------

    /**
     * Distances as normalized array from comma-separated string.
     * Example: "9m, 18m" => ["9m","18m"]
     */
    public function distancesArray(): array
    {
        $raw = (string) ($this->distances ?? '');
        $parts = array_map(fn ($x) => strtolower(trim($x)), explode(',', $raw));
        $parts = array_values(array_filter($parts, fn ($x) => $x !== ''));

        // Normalize common spacing: "18 m" -> "18m"
        $parts = array_map(fn ($x) => preg_replace('/\s+/', '', $x), $parts);

        return $parts;
    }
}
