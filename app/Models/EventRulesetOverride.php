<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRulesetOverride extends Model
{
    protected $fillable = ['event_id', 'overrides'];

    protected $casts = ['overrides' => 'array'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
