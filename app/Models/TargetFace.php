<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TargetFace extends Model
{
    protected $table = 'target_faces';

    protected $fillable = ['key', 'label', 'kind', 'diameter_cm', 'zones'];

    public function rulesets(): BelongsToMany
    {
        return $this->belongsToMany(Ruleset::class, 'ruleset_target_face');
    }
}
