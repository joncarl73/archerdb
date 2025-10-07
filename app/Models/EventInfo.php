<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventInfo extends Model
{
    protected $fillable = ['event_id', 'title', 'registration_url', 'banner_path', 'content_html', 'is_published'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
