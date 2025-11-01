<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Discipline extends Model
{
    protected $table = 'disciplines';

    protected $fillable = ['key', 'label'];

    public function rulesets(): BelongsToMany
    {
        return $this->belongsToMany(Ruleset::class, 'ruleset_discipline');
    }
}
