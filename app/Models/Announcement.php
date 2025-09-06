<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'title_ar', 'content', 'content_ar', 'type', 'priority',
        'is_pinned', 'is_active', 'start_datetime', 'end_datetime', 'created_by'
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_datetime', '<=', now())
            ->where(function($q) {
                $q->whereNull('end_datetime')
                    ->orWhere('end_datetime', '>', now());
            });
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}