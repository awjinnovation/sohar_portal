<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTag extends Model
{
    protected $fillable = ['event_id', 'tag', 'tag_ar'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}