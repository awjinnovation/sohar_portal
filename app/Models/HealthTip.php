<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthTip extends Model
{
    protected $fillable = [
        'title',
        'title_ar',
        'content',
        'content_ar',
        'category',
        'icon',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
