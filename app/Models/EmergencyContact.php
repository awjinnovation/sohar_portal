<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    protected $fillable = [
        'service_name', 'service_name_ar', 'phone_number', 'secondary_phone',
        'type', 'location', 'location_ar', 'is_24_hours', 'display_order', 'is_active'
    ];

    protected $casts = [
        'is_24_hours' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }
}