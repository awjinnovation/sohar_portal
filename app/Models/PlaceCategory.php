<?php

namespace App\Models;

use App\Models\Concerns\LocalizedContent;
use Illuminate\Database\Eloquent\Model;

/**
 * Category of places to visit (docs/places_content_contract.md §2).
 *
 * @property array $name {en, ar}
 * @property string $icon Material Symbols Rounded name
 * @property string $color #RRGGBB
 */
class PlaceCategory extends Model
{
    use \App\Tenancy\BelongsToTenant;
    use LocalizedContent;

    protected $fillable = ['key', 'name', 'icon', 'color', 'sort', 'is_active'];

    protected $casts = [
        'name' => 'array',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function places()
    {
        return $this->hasMany(Place::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
