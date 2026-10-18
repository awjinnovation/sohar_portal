<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayToken extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = ['jti', 'user_id', 'wallet_id', 'expires_at', 'used_at', 'used_by'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}
