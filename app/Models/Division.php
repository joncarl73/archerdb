<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Division extends Model
{
    protected $table = 'divisions';

    protected $fillable = ['key', 'label'];

    public function rulesets(): BelongsToMany
    {
        return $this->belongsToMany(Ruleset::class, 'ruleset_division');
    }
}
