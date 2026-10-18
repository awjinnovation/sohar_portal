<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use \App\Tenancy\BelongsToTenant;

    protected $fillable = [
        'user_id',
        'title',
        'title_ar',
        'body',
        'body_ar',
        'type',
        'data',
        'is_read',
        'read_at',
        'is_public',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
