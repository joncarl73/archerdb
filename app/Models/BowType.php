<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BowType extends Model
{
    protected $table = 'bow_types';

    protected $fillable = ['key', 'label'];

    public function rulesets(): BelongsToMany
    {
        return $this->belongsToMany(Ruleset::class, 'ruleset_bow_type');
    }
}
