<?php

namespace App\Models;

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
}
