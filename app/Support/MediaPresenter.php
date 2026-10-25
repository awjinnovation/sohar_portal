<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Image shape of the places/content API: {id, url, thumb_url, width, height}.
 * URLs of the public disk are built from the request host (asset()), so they
 * work whatever APP_URL is set to (php artisan serve on another port, LAN IP).
 */
class MediaPresenter
{
    public static function image(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }

        $url = self::url($media);
        $thumb = $media->hasGeneratedConversion('thumb') ? self::url($media, 'thumb') : $url;
        $size = [$media->getCustomProperty('width'), $media->getCustomProperty('height')];
        if ($size[0] === null) {
            // Uploaded outside the admin (seeders, tinker): read the file once and remember it.
            self::rememberSize($media);
            $size = [$media->getCustomProperty('width'), $media->getCustomProperty('height')];
        }

        return [
            'id' => $media->id,
            'url' => $url,
            'thumb_url' => $thumb,
            'width' => $size[0],
            'height' => $size[1],
        ];
    }

    public static function url(Media $media, string $conversion = ''): string
    {
        if ($media->disk === 'public') {
            return asset('storage/'.str_replace('\\', '/', $media->getPathRelativeToRoot($conversion)));
        }

        return $conversion !== '' ? $media->getUrl($conversion) : $media->getUrl();
    }

    /** Store the original pixel size as custom properties (cheap, read once at upload). */
    public static function rememberSize(Media $media): Media
    {
        try {
            $size = @getimagesize($media->getPath());
            if ($size) {
                $media->setCustomProperty('width', (int) $size[0])->setCustomProperty('height', (int) $size[1])->save();
            }
        } catch (\Throwable) {
            // Size is optional in the API.
        }

        return $media;
    }
}
