<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['seller_id', 'productable_type', 'productable_id', 'name', 'currency', 'price_cents', 'platform_fee_bps', 'settlement_mode', 'metadata', 'is_active'];

    protected $casts = ['metadata' => 'array'];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }
}
