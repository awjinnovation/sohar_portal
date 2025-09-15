<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'thawani_session_id',
        'payment_type',
        'payable_id',
        'payable_type',
        'amount',
        'currency',
        'status',
        'thawani_response',
        'metadata',
        'payment_method',
        'paid_at'
    ];

    protected $casts = [
        'thawani_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payable()
    {
        return $this->morphTo();
    }
}