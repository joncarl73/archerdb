<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RulesetClass extends Model
{
    // Table is named 'classes' (not 'ruleset_classes'), so specify it:
    protected $table = 'classes';

    protected $fillable = ['key', 'label'];

    public function rulesets(): BelongsToMany
    {
        return $this->belongsToMany(Ruleset::class, 'ruleset_class', 'class_id', 'ruleset_id');
    }
}
