<?php

namespace App\Models;

use App\Models\Concerns\LocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Standalone page (about the governorate, visiting guidelines...), shown
 * in Discover and optionally in the app's More screen (contract §2).
 */
class ContentPage extends Model implements HasMedia
{
    use \App\Tenancy\BelongsToTenant;
    use \App\Models\Concerns\BustsPublicApiCache;
    use InteractsWithMedia;
    use LocalizedContent;

    protected $fillable = ['slug', 'title', 'body', 'icon', 'show_in_more', 'sort', 'status', 'published_at'];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'show_in_more' => 'boolean',
        'sort' => 'integer',
        'published_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->useDisk('public')->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 640, 420)
            ->format('webp')
            ->quality(80)
            ->performOnCollections('cover')
            ->nonQueued();
    }
}
