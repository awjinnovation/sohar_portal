<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotographyTip extends Model
{
    protected $fillable = [
        'photo_spot_id',
        'tip',
        'display_order',
    ];

    public function photoSpot()
    {
        return $this->belongsTo(PhotoSpot::class);
    }
}
