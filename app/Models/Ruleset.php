<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ruleset extends Model
{
    protected $fillable = [
        'company_id', 'org', 'name', 'description', 'schema', 'scoring_values', 'x_value', 'distances_m', 'ends_per_session', 'arrows_per_end', 'lane_breakdown', 'lanes_count',
    ];

    protected $casts = [
        'schema' => 'array',   // JSON <-> array
        'scoring_values' => 'array',
        'x_value' => 'integer',
        'distances_m' => 'array',
        'ends_per_session' => 'integer',
        'arrows_per_end' => 'integer',
        'lanes_count' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /* ----- Scopes ----- */

    // Global canned rulesets
    public function scopeCanned($q)
    {
        return $q->whereNull('company_id')->where('is_system', true);
    }

    // Custom rulesets for a specific company
    public function scopeForCompany($q, $companyId)
    {
        return $q->where('company_id', $companyId);
    }

    // Everything visible to a company: canned + company custom
    public function scopeVisibleTo($q, $companyId)
    {
        return $q->where(function ($w) use ($companyId) {
            $w->whereNull('company_id')->orWhere('company_id', $companyId);
        });
    }

    public function disciplines()
    {
        return $this->belongsToMany(\App\Models\Discipline::class, 'ruleset_discipline');
    }

    public function bowTypes()
    {
        return $this->belongsToMany(\App\Models\BowType::class, 'ruleset_bow_type');
    }

    public function targetFaces()
    {
        return $this->belongsToMany(\App\Models\TargetFace::class, 'ruleset_target_face');
    }

    public function divisions()
    {
        return $this->belongsToMany(\App\Models\Division::class, 'ruleset_division');
    }

    public function classes()
    {
        return $this->belongsToMany(\App\Models\RulesetClass::class, 'ruleset_class', 'ruleset_id', 'class_id');
    }
}
