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

        // Stored in DB as JSON array-of-arrays, but we treat it internally as:
        // "slot letters per lane" (CSV) -> stored as [[A,B]] and expanded in code.
        'lane_slot_groups',

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

    // ---------------- CSV slots convenience ----------------

    /**
     * User-friendly slots input (CSV).
     * Examples:
     *  - "A,B" => slots A and B for every lane
     *  - ""    => no slots (lane options are just 1,2,3...)
     */
    public function getLaneSlotsCsvAttribute(): string
    {
        $groups = is_array($this->lane_slot_groups) ? $this->lane_slot_groups : [];

        // We treat the first group as the canonical list of slot labels.
        // (We expand it to all lanes_per_bale dynamically in laneSlotGroups()).
        $first = $groups[0] ?? [];
        $first = is_array($first) ? $first : [];

        $slots = array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            $first
        ), fn ($x) => $x !== ''));

        return implode(',', $slots);
    }

    /**
     * Set lane slots via CSV (stored into lane_slot_groups as [[...]] in DB).
     */
    public function setLaneSlotsCsvAttribute(?string $value): void
    {
        $slots = self::parseSlotsCsv($value);

        // Store as array-of-arrays to match DB format.
        // We store ONE group and expand at runtime based on lanes_per_bale.
        $this->attributes['lane_slot_groups'] = json_encode(
            $slots ? [$slots] : [],
            JSON_UNESCAPED_UNICODE
        );
    }

    public static function parseSlotsCsv(?string $value): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $parts = array_map(fn ($x) => strtoupper(trim((string) $x)), $parts);
        $parts = array_values(array_filter($parts, fn ($x) => $x !== ''));

        // De-dupe while preserving order
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            if (! isset($seen[$p])) {
                $seen[$p] = true;
                $out[] = $p;
            }
        }

        return $out;
    }

    // ---------------- Core computed values ----------------

    public function lanesCount(): int
    {
        return max(1, (int) $this->bales_count) * max(1, (int) $this->lanes_per_bale);
    }

    /**
     * Returns lane slot groups normalized.
     *
     * IMPORTANT:
     * - We now treat the range as having "slot letters per lane" (A,B, etc).
     * - We store lane_slot_groups in DB as [[A,B]] (one group),
     *   and EXPAND it here to match lanes_per_bale by repeating that group.
     *
     * This fixes lane dropdown skipping (1A,1B,3A,3B...) and makes it:
     * 1A,1B,2A,2B,3A,3B...
     */
    public function laneSlotGroups(): array
    {
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

        // If DB contains a flat array like ["A","B"], normalize to [["A","B"]]
        if ($groups && ! is_array($groups[0] ?? null)) {
            $groups = [$groups];
        }

        // Pull canonical slots from the first group
        $slots = $groups[0] ?? [];

        // If nothing configured, default to NO slots (lane numbers only).
        // (This matches your new "blank means 1 lane per bale / no slot letters" preference.)
        if (! is_array($slots) || count($slots) === 0) {
            $out = [];
            for ($i = 0; $i < $lanesPerBale; $i++) {
                $out[] = [];
            }

            return $out;
        }

        // Expand to lanes_per_bale by repeating the same slots for each lane
        $expanded = [];
        for ($i = 0; $i < $lanesPerBale; $i++) {
            $expanded[] = $slots;
        }

        return $expanded;
    }

    /**
     * Total shooting positions across the whole range.
     * Positions per bale = lanes_per_bale * slots_per_lane (or lanes_per_bale if blank slots).
     */
    public function positionsCount(): int
    {
        $groups = $this->laneSlotGroups();
        $lanesPerBale = max(1, (int) $this->lanes_per_bale);

        // If slots blank, each lane contributes 1 position (just lane number)
        $slotsPerLane = 0;
        if (isset($groups[0]) && is_array($groups[0])) {
            $slotsPerLane = count($groups[0]);
        }
        $perBale = $slotsPerLane > 0 ? ($lanesPerBale * $slotsPerLane) : $lanesPerBale;

        return max(1, (int) $this->bales_count) * max(1, (int) $perBale);
    }

    /**
     * Build CLS lane options: lane_number + slot label (or just lane_number if slots are blank).
     * Returns: ["1A","1B","2A","2B", ...] OR ["1","2","3", ...]
     */
    public function laneOptions(): array
    {
        $options = [];

        $bales = max(1, (int) $this->bales_count);
        $lanesPerBale = max(1, (int) $this->lanes_per_bale);

        // We store ONE array in lane_slot_groups, e.g. [["A","B"]]
        $groups = is_array($this->lane_slot_groups) ? $this->lane_slot_groups : [];
        $labels = (isset($groups[0]) && is_array($groups[0])) ? $groups[0] : [];

        // Normalize labels (uppercase, trimmed, remove blanks)
        $labels = array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            $labels
        ), fn ($x) => $x !== ''));

        // If lanes_per_bale == 1 and no labels, show just bale numbers: 1..N
        if ($lanesPerBale === 1 && count($labels) === 0) {
            for ($bale = 1; $bale <= $bales; $bale++) {
                $options[] = (string) $bale;
            }

            return $options;
        }

        // If labels are missing/short, generate A,B,C... up to lanesPerBale
        if (count($labels) < $lanesPerBale) {
            $alpha = range('A', 'Z');
            for ($i = count($labels); $i < $lanesPerBale; $i++) {
                $labels[] = $alpha[$i] ?? ('L'.($i + 1)); // fallback if > 26
            }
        }

        // Build: 1A,1B..12A,12B (lane_number=bale number)
        for ($bale = 1; $bale <= $bales; $bale++) {
            for ($i = 0; $i < $lanesPerBale; $i++) {
                $options[] = (string) $bale.$labels[$i];
            }
        }

        return $options;
    }

    // ---------------- Convenience helpers ----------------

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
