<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkshopSchedule extends Model
{
    protected $table = 'workshop_schedule';
    
    protected $fillable = [
        'workshop_id',
        'schedule_time',
    ];

    protected $casts = [
        'schedule_time' => 'datetime:H:i',
    ];

    public function culturalWorkshop()
    {
        return $this->belongsTo(CulturalWorkshop::class, 'workshop_id');
    }
}
