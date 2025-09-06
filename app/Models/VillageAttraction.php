<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillageAttraction extends Model
{
    protected $fillable = ['village_id', 'attraction_en', 'attraction_ar', 'display_order'];

    protected $casts = [
        'display_order' => 'integer'
    ];

    public function village()
    {
        return $this->belongsTo(HeritageVillage::class, 'village_id');
    }
}