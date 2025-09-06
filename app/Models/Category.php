<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'description', 'description_ar',
        'icon_name', 'color_value', 'image_url', 'display_order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'color_value' => 'integer',
        'display_order' => 'integer'
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function userInterests()
    {
        return $this->hasMany(UserInterest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }
}