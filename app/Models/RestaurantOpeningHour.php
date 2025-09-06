<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantOpeningHour extends Model
{
    protected $fillable = [
        'restaurant_id', 'day_of_week', 'open_time', 'close_time', 'is_closed'
    ];

    protected $casts = [
        'is_closed' => 'boolean'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}