<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ruleset extends Model
{
    protected $fillable = [
        'company_id', 'org', 'name', 'slug', 'description', 'is_system', 'schema',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'schema' => 'array',   // JSON <-> array
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
}
