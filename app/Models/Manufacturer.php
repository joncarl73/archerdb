<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['name','categories','website','country'];
    protected $casts = ['categories' => 'array'];
}
