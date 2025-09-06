<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CraftDemonstrationSchedule extends Model
{
    protected $table = 'craft_demonstration_schedule';
    
    protected $fillable = [
        'demonstration_id',
        'schedule_time',
    ];

    protected $casts = [
        'schedule_time' => 'datetime:H:i',
    ];

    public function craftDemonstration()
    {
        return $this->belongsTo(CraftDemonstration::class, 'demonstration_id');
    }
}
