<?php

// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'owner_user_id',
        'company_name', 'legal_name', 'website', 'support_email', 'phone',
        'address_line1', 'address_line2', 'city', 'state_region', 'postal_code', 'country',
        'industry', 'logo_path', 'completed_at',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(\App\Models\League::class);
    }
}
