<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapLocation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'latitude',
        'longitude',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];
}
