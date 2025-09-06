<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationCategory extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'icon',
        'color',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
