<?php
// app/Models/ArcherProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcherProfile extends Model
{
    protected $fillable = [
        'user_id','gender','birth_date','handedness',
        'para_archer','uses_wheelchair','club_affiliation',
        'us_archery_number','completed_at',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
