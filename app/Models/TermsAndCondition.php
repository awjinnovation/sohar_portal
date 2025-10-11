<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsAndCondition extends Model
{
    protected $fillable = [
        'type',
        'language',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLanguage($query, $language = 'en')
    {
        return $query->where('language', $language);
    }

    public function scopeByType($query, $type = 'general')
    {
        return $query->where('type', $type);
    }
}
