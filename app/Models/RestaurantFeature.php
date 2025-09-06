<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantFeature extends Model
{
    protected $fillable = ['restaurant_id', 'feature', 'feature_ar'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}