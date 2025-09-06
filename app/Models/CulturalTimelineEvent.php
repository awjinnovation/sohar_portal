<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulturalTimelineEvent extends Model
{
    protected $fillable = [
        'year',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'image_url',
        'category',
        'is_key_milestone',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_key_milestone' => 'boolean',
        'is_active' => 'boolean',
    ];
}
