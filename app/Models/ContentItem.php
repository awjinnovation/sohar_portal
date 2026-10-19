<?php

namespace App\Models;

use App\Models\Concerns\LocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One entry of a content collection (contract §2). Location: a linked place
 * (place_id) or its own latitude/longitude. Media: `image` (single; the
 * logo for brands, kept uncropped) and `gallery`.
 */
class ContentItem extends Model implements HasMedia
{
    use \App\Tenancy\BelongsToTenant;
    use InteractsWithMedia;
    use LocalizedContent;

    protected $fillable = [
        'collection_id', 'slug', 'title', 'subtitle', 'body', 'icon', 'link_url', 'link_label', 'phone', 'email',
        'place_id', 'latitude', 'longitude', 'tags', 'featured', 'sort', 'status', 'published_at',
    ];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'body' => 'array',
        'link_label' => 'array',
        'tags' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'featured' => 'boolean',
        'sort' => 'integer',
        'published_at' => 'datetime',
    ];

    public function collection()
    {
        return $this->belongsTo(ContentCollection::class, 'collection_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->useDisk('public')->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        $this->addMediaCollection('gallery')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Logos must not be cropped: fit inside the box, keep transparency (webp).
        $gallery = $media?->collection_name === 'gallery';
        $this->addMediaConversion('thumb')
            ->fit($gallery ? Fit::Crop : Fit::Contain, $gallery ? 640 : 480, $gallery ? 420 : 480)
            ->format('webp')
            ->quality(85)
            ->performOnCollections('image', 'gallery')
            ->nonQueued();
    }
}
