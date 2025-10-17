<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    protected $fillable = ['owner_id', 'name', 'stripe_account_id', 'default_platform_fee_bps', 'default_platform_fee_cents', 'active'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
