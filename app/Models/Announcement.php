<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use \App\Tenancy\BelongsToTenant;

    /**
     * Banner types offered by the admin. The app maps warning → warning,
     * emergency (and legacy alert/critical) → critical, anything else → info.
     */
    public const TYPES = ['info', 'warning', 'emergency', 'celebration'];

    /** Arabic admin labels (translated via lang/en.json). */
    public const TYPE_LABELS = ['info' => 'معلومات', 'warning' => 'تحذير', 'emergency' => 'طوارئ', 'celebration' => 'احتفال'];

    protected $fillable = [
        'title', 'title_ar', 'content', 'content_ar', 'type', 'priority',
        'is_pinned', 'is_active', 'start_datetime', 'end_datetime', 'created_by'
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        // Wall-clock times in the tenant time zone (see TenantLocalDateTime).
        'start_datetime' => \App\Casts\TenantLocalDateTime::class,
        'end_datetime' => \App\Casts\TenantLocalDateTime::class,
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_datetime', '<=', \App\Support\TenantTime::localNow())
            ->where(function($q) {
                $q->whereNull('end_datetime')
                    ->orWhere('end_datetime', '>', \App\Support\TenantTime::localNow());
            });
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}