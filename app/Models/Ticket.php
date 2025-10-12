<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'booking_date', 'quantity', 'ticket_type', 'status', 'price', 'currency',
        'holder_name', 'seat_number', 'qr_code', 'purchase_date', 'used_at'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'price' => 'decimal:3',
        'purchase_date' => 'datetime',
        'used_at' => 'datetime'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}