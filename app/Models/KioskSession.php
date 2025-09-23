<?php

// app/Models/KioskSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskSession extends Model
{
    protected $fillable = [
        'league_id', 'week_number', 'lanes', 'token', 'is_active', 'created_by', 'expires_at', 'participants',
    ];

    protected $casts = [
        'lanes' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'participants' => 'array',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }
}
