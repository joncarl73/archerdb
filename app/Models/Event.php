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
}
