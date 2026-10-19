<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** "Plan my visit" result (shareable by its uuid inside the tenant). */
class AiPlan extends Model
{
    use \App\Tenancy\BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'guest_key', 'locale', 'date', 'request', 'plan'];

    protected $hidden = ['guest_key'];

    protected $casts = [
        'date' => 'date',
        'request' => 'array',
        'plan' => 'array',
    ];
}
