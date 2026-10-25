<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsAccount extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = ['user_id'];

    protected $casts = ['balance' => 'integer', 'lifetime_earned' => 'integer'];

    protected $attributes = ['balance' => 0, 'lifetime_earned' => 0];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(PointsTransaction::class);
    }
}
