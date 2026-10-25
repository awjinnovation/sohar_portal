<?php

namespace App\Models;

use App\Models\Concerns\LocalizedContent;
use Illuminate\Database\Eloquent\Model;

/**
 * A list of content items (services, partner brands, visitor guide...)
 * rendered by the app with its `layout` (contract §2).
 */
class ContentCollection extends Model
{
    use \App\Tenancy\BelongsToTenant;
    use LocalizedContent;

    public const LAYOUTS = ['list', 'grid', 'logos', 'cards'];

    protected $fillable = ['key', 'name', 'description', 'icon', 'layout', 'show_in_discover', 'sort', 'is_active'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'show_in_discover' => 'boolean',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ContentItem::class, 'collection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
