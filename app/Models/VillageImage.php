<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillageImage extends Model
{
    protected $fillable = ['village_id', 'image_url', 'display_order'];

    protected $casts = [
        'display_order' => 'integer'
    ];

    public function village()
    {
        return $this->belongsTo(HeritageVillage::class, 'village_id');
    }
}