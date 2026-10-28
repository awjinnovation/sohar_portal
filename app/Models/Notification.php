<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use \App\Tenancy\BelongsToTenant;
    use \App\Models\Concerns\BustsPublicApiCache;

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

    /** Only public notifications are in the shared API cache (personal ones are written all the time). */
    public function affectsPublicApiCache(): bool
    {
        return (bool) ($this->is_public || $this->getOriginal('is_public'));
    }
}
