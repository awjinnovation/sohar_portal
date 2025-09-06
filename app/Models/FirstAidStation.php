<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirstAidStation extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'location',
        'location_ar',
        'latitude',
        'longitude',
        'operating_hours',
        'contact_number',
        'services_available',
        'services_available_ar',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];
}
