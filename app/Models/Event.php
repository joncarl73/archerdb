<?php

namespace App\Models;

use App\Enums\EventKind;
use App\Enums\EventScoringMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_uuid', 'owner_id', 'title', 'kind', 'scoring_mode', 'is_published', 'starts_on', 'ends_on',
    ];

    protected $casts = [
        'is_published' => 'bool',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'kind' => EventKind::class,
        'scoring_mode' => EventScoringMode::class,
    ];

    public function league(): HasOne
    {
        return $this->hasOne(League::class);
    }

    public function divisions()
    {
        return $this->hasMany(\App\Models\EventDivision::class);
    }

    public function lineTimes()
    {
        return $this->hasMany(\App\Models\EventLineTime::class);
    }

    public function laneMaps()
    {
        return $this->hasMany(\App\Models\EventLaneMap::class);
    }

    public function products()
    {
        return $this->morphMany(\App\Models\Product::class, 'productable');
    }

    public function info(): HasOne
    {
        return $this->hasOne(\App\Models\EventInfo::class);
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    /**
     * Normalize scoring_mode:
     * - 'personal' or 'pd' => 'personal_device'
     * - leave 'personal_device', 'kiosk', 'tablet' as-is
     * - default to 'personal_device' if empty/unknown
     */
    protected function scoringMode(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $v = strtolower((string) $value);
                if (in_array($v, ['personal', 'pd'], true)) {
                    return 'personal_device';
                }
                if ($v === '') {
                    return 'personal_device';
                }

                return $v;
            },
            set: function ($value) {
                $v = is_string($value) ? strtolower($value) : '';
                if (in_array($v, ['personal', 'pd'], true)) {
                    $v = 'personal_device';
                }
                if ($v === '') {
                    $v = 'personal_device';
                }

                return ['scoring_mode' => $v];
            }
        );
    }

    // Optional convenience helpers:
    public function isPersonalDevice(): bool
    {
        return $this->scoring_mode === 'personal_device';
    }

    public function isKioskLike(): bool
    {
        // Treat either label as kiosk-style
        return in_array($this->scoring_mode, ['kiosk', 'tablet'], true);
    }

    /**
     * Human-friendly label for scoring mode.
     */
    public function getScoringModeLabelAttribute(): string
    {
        $sm = $this->scoring_mode instanceof \UnitEnum ? $this->scoring_mode->value : (string) $this->scoring_mode;

        return $sm === 'kiosk' ? 'Kiosk/Tablet' : 'Personal Device';
        // use in blade as: {{ $event->scoring_mode_label }}
    }
}
