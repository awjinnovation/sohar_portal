<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'description',
        'description_ar',
        'latitude',
        'longitude',
        'address',
        'address_ar',
        'contact_number',
        'additional_info',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'additional_info' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEmergency($query)
    {
        return $query->where('type', 'emergency');
    }

    public function scopeServices($query)
    {
        return $query->whereIn('type', ['service', 'parking', 'restroom', 'first_aid']);
    }
}