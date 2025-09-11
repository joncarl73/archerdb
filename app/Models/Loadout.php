<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loadout extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id','name','is_primary','bow_type','notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(LoadoutItem::class)->orderBy('position');
    }

    protected static function booted()
    {
        static::deleting(fn($loadout) => $loadout->items()->delete());
        static::restoring(fn($loadout) => $loadout->items()->withTrashed()->restore());
    }

}
