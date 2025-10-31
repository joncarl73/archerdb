<?php

namespace App\Models;

use App\Enums\EventKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'public_uuid', 'title', 'location',
        'kind', 'starts_on', 'ends_on', 'is_published', 'scoring_mode',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_published' => 'bool',
        'kind' => EventKind::class, // backed enum cast (string)
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! $model->public_uuid) {
                $model->public_uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships (stubs for future phases)
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Helpful derived checks
    public function isSingleDay(): bool
    {
        return $this->kind === EventKind::SingleDay;
    }

    public function ruleset()
    {
        return $this->belongsTo(Ruleset::class);
    }

    public function rulesetOverrides()
    {
        return $this->hasOne(EventRulesetOverride::class);
    }

    public function effectiveRules(): array
    {
        $base = $this->ruleset?->schema ?? [];
        $ovr = $this->rulesetOverrides?->overrides ?? [];

        return \App\Support\RulesetResolver::deepMerge($base, $ovr);
    }

    public function collaborators()
    {
        // Matches league_users style but for events
        return $this->belongsToMany(User::class, 'event_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners()
    {
        return $this->collaborators()->wherePivot('role', 'owner');
    }

    public function managers()
    {
        return $this->collaborators()->wherePivot('role', 'manager');
    }

    public function userRoleFor(User $user): ?string
    {
        $p = $this->collaborators()->where('user_id', $user->id)->first()?->pivot;

        return $p?->role;
    }

    public function belongsToSameCompany(User $user): bool
    {
        return (int) $this->company_id === (int) $user->company_id;
    }

    public function lineTimes()
    {
        return $this->hasMany(\App\Models\EventLineTime::class)->orderBy('line_date')->orderBy('start_time');
    }
}
