<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeagueInfo extends Model
{
    protected $fillable = [
        'league_id', 'title', 'registration_url', 'banner_path', 'content_html', 'is_published',
    ];

    public function league()
    {
        return $this->belongsTo(League::class);
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }
}
