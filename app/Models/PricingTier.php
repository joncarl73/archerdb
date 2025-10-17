<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'league_participant_fee_cents',
        'competition_participant_fee_cents',
        'currency',
        'is_active',
        'notes',
    ];
}
