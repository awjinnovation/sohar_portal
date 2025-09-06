<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantImage extends Model
{
    protected $fillable = ['restaurant_id', 'image_url', 'display_order'];

    protected $casts = [
        'display_order' => 'integer'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}