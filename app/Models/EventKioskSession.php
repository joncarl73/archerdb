<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventKioskSession extends Model
{
    use HasFactory;

    protected $table = 'event_kiosk_sessions';

    protected $fillable = [
        'event_id',
        'event_line_time_id',
        'participants',
        'lanes',
        'token',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'participants' => 'array',
        'lanes' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lineTime()
    {
        return $this->belongsTo(EventLineTime::class, 'event_line_time_id');
    }

    // Scopes
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
