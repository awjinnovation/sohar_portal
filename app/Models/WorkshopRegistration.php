<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workshop_id'
    ];

    /**
     * Get the user for this registration
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the workshop for this registration
     */
    public function workshop()
    {
        return $this->belongsTo(CulturalWorkshop::class, 'workshop_id');
    }
}