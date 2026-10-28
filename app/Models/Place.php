<?php

namespace App\Models;

use App\Models\Concerns\LocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A place to visit on the organizer's custom map (contract §2). Media:
 * `cover` (single) and `gallery` (ordered); both get a `thumb` conversion
 * generated synchronously (no queue worker needed).
 *
 * @property array $name
 * @property array|null $summary
 * @property array|null $description sanitized HTML per language
 */
class Place extends Model implements HasMedia
{
    use \App\Tenancy\BelongsToTenant;
    use \App\Models\Concerns\BustsPublicApiCache;
    use InteractsWithMedia;
    use LocalizedContent;

    protected $fillable = [
        'category_id', 'slug', 'name', 'summary', 'description', 'latitude', 'longitude', 'address', 'wilayat',
        'link_url', 'link_label', 'phone', 'opening_hours', 'entry_fee_minor', 'icon', 'featured', 'sort',
        'status', 'published_at',
    ];

    protected $casts = [
        'name' => 'array',
        'summary' => 'array',
        'description' => 'array',
        'address' => 'array',
        'wilayat' => 'array',
        'link_label' => 'array',
        'opening_hours' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'entry_fee_minor' => 'integer',
        'featured' => 'boolean',
        'sort' => 'integer',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(PlaceCategory::class, 'category_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_place');
    }

    public function contentItems()
    {
        return $this->hasMany(ContentItem::class);
    }

    /** Effective icon: own override, else the category icon. */
    public function displayIcon(): string
    {
        return $this->icon ?: ($this->category?->icon ?: 'place');
    }

    public function displayColor(): string
    {
        return $this->category?->color ?: '#2F8E80';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->useDisk('public')->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        $this->addMediaCollection('gallery')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 640, 420)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('cover', 'gallery')
            ->nonQueued();
    }
}
