<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsTransaction extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'remaining' => 'integer',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(PointsAccount::class, 'points_account_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
