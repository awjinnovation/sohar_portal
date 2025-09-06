<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPricing extends Model
{
    protected $table = 'ticket_pricing';
    
    protected $fillable = [
        'event_id', 'ticket_type', 'price', 'available_quantity', 'benefits', 'benefits_ar'
    ];

    protected $casts = [
        'price' => 'decimal:3',
        'available_quantity' => 'integer'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}