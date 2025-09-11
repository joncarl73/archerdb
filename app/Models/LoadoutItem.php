<?php
// app/Models/LoadoutItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LoadoutItem extends Model {
    protected $fillable = ['loadout_id','category','manufacturer_id','model','specs','position'];
    protected $casts = ['specs'=>'array'];
    public function loadout() { return $this->belongsTo(Loadout::class); }
    public function manufacturer() { return $this->belongsTo(Manufacturer::class); }
}

