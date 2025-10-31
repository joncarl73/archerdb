<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ruleset extends Model
{
    protected $fillable = ['org', 'name', 'slug', 'description', 'is_system', 'schema'];

    protected $casts = ['is_system' => 'bool', 'schema' => 'array'];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
