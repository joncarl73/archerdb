<?php
// app/Models/ArcherProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcherProfile extends Model
{
    protected $fillable = [
        'user_id','gender','birth_date','handedness',
        'para_archer','uses_wheelchair','club_affiliation',
        'us_archery_number','country','completed_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
